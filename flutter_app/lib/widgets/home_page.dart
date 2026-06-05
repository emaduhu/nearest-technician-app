import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';
import '../l10n/app_localizations.dart';
import '../services/fcm_service.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class HomePage extends StatefulWidget {
  final Map<String, dynamic> session;
  final Map<String, dynamic>? initialNotification;
  final VoidCallback onLogout;
  final Locale locale;
  final ValueChanged<Locale> onLocaleChanged;

  const HomePage({
    required this.session,
    this.initialNotification,
    required this.onLogout,
    required this.locale,
    required this.onLocaleChanged,
    super.key,
  });

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final ApiService _api = ApiService();
  final FcmService _fcm = FcmService();
  final TextEditingController _searchCtrl = TextEditingController();
  final TextEditingController _descriptionCtrl = TextEditingController();
  final TextEditingController _paymentPhoneCtrl = TextEditingController();
  Timer? _debounce;
  Timer? _historyTimer;
  Timer? _trackingTimer;
  StreamSubscription<Position>? _locationSub;

  String _status = '';
  bool _loading = false;
  bool _paymentLoading = false;
  bool _available = true;
  bool _onlyAvailable = true;
  double _maxDistanceKm = 25;
  double _minRating = 0;
  String _selectedPaymentOperator = 'Yas';
  String? _deviceToken;
  Map<String, dynamic>? _registrationPayment;
  List<dynamic> _results = [];
  List<dynamic> _history = [];
  final List<_AppNotification> _notifications = [];
  int _unreadNotifications = 0;

  static const List<String> _paymentOperators = ['Yas', 'Airtel', 'Halopesa'];

  Map<String, dynamic> get _user =>
      Map<String, dynamic>.from(widget.session['user'] as Map);
  Map<String, dynamic>? get _technician {
    final tech = widget.session['technician'];
    return tech == null ? null : Map<String, dynamic>.from(tech as Map);
  }

  bool get _isTechnician => _user['role'] == 'technician';
  Map<String, dynamic> get _registrationReview {
    final review = _technician?['registrationReview'];
    return review is Map ? Map<String, dynamic>.from(review) : {};
  }

  String get _registrationReviewStatus =>
      _registrationReview['status']?.toString() ?? 'approved';
  bool get _technicianApproved =>
      !_isTechnician || _registrationReviewStatus == 'approved';
  bool get _clientRequestsBlocked {
    final value = _technician?['clientRequestsBlocked'];
    return value == true || value?.toString() == 'true' || value == 1;
  }

  String get _clientRequestsBlockedReason =>
      _technician?['clientRequestsBlockedReason']?.toString() ?? '';

  bool get _hasAcceptedRequest =>
      _history.any((item) => item is Map && item['status'] == 'accepted');

  @override
  void initState() {
    super.initState();
    final sessionPayment = widget.session['registrationPayment'];
    _registrationPayment = sessionPayment is Map
        ? Map<String, dynamic>.from(sessionPayment)
        : _technician?['registrationPayment'] is Map
            ? Map<String, dynamic>.from(
                _technician!['registrationPayment'] as Map)
            : null;
    _paymentPhoneCtrl.text =
        _localTanzaniaPhone(_technician?['phone'] ?? _user['phone'] ?? '');
    _available =
        _clientRequestsBlocked ? false : _technician?['available'] == true;
    _initializeForegroundUpdates();
    if (widget.initialNotification != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _handleIncomingNotification(widget.initialNotification!);
        }
      });
    }
    if (!_isTechnician || _technicianApproved) {
      _loadHistory();
    }
    _startLiveLocation();
    _trackingTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      if (!_isTechnician && _hasAcceptedRequest) {
        _loadHistory();
      }
    });
  }

  Future<void> _initializeForegroundUpdates() async {
    final token = await _fcm.init(_handleIncomingNotification,
        onTokenRefresh: _saveDeviceToken);
    if (token != null && token.isNotEmpty) {
      await _saveDeviceToken(token);
    }
    _historyTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      _loadHistory();
    });
  }

  void _handleIncomingNotification(Map<String, dynamic> data) {
    if (!mounted) return;
    final type = data['type']?.toString() ?? '';
    if (type != 'request_response' &&
        type != 'tech_request' &&
        type != 'service_request' &&
        type != 'technician_response' &&
        type != 'registration_review' &&
        type != 'portal_test' &&
        type != 'portal_warning' &&
        type != 'portal_news') {
      return;
    }

    _recordNotification(data);

    if (type == 'registration_review') {
      _applyRegistrationReviewPush(data);
      return;
    }
    if (_isBlockedTechnicianRequestPush(type)) {
      return;
    }

    final l10n = AppLocalizations.of(context);
    setState(() {
      if (type == 'portal_test' ||
          type == 'portal_warning' ||
          type == 'portal_news') {
        _status = data['body']?.toString() ?? l10n.newStatus;
      } else if (type == 'request_response' || type == 'technician_response') {
        final status = data['status']?.toString() ?? l10n.newStatus;
        _status = (status == 'accepted' || status == 'rejected') &&
                data['body'] != null
            ? data['body'].toString()
            : '${l10n.requestUpdated}: ${l10n.statusLabel(status)}';
      } else {
        _status = data['body']?.toString() ?? l10n.newRequestReceived;
      }
    });
    _loadHistory();
  }

  void _recordNotification(Map<String, dynamic> data) {
    final l10n = AppLocalizations.of(context);
    final type = data['type']?.toString() ?? '';
    final title = data['title']?.toString().trim();
    final body = data['body']?.toString().trim();
    final notification = _AppNotification(
      type: type,
      title: title == null || title.isEmpty
          ? _notificationFallbackTitle(type, l10n)
          : title,
      body: body == null || body.isEmpty ? l10n.notificationReceived : body,
      receivedAtLabel: TimeOfDay.now().format(context),
    );

    setState(() {
      _notifications.insert(0, notification);
      if (_notifications.length > 100) {
        _notifications.removeLast();
      }
      _unreadNotifications = math.min(_unreadNotifications + 1, 99);
    });
  }

  String _notificationFallbackTitle(String type, AppLocalizations l10n) {
    switch (type) {
      case 'tech_request':
      case 'service_request':
        return l10n.newRequestReceived;
      case 'request_response':
      case 'technician_response':
        return l10n.requestUpdated;
      case 'registration_review':
        return l10n.registrationReviewTitle;
      case 'portal_warning':
        return l10n.accountWarning;
      case 'portal_news':
        return l10n.news;
      default:
        return l10n.notifications;
    }
  }

  void _applyRegistrationReviewPush(Map<String, dynamic> data) {
    final l10n = AppLocalizations.of(context);
    final status = data['status']?.toString() ?? '';
    if (!['approved', 'rejected', 'pending'].contains(status)) {
      return;
    }

    final localTechnicianId = _technician?['id']?.toString() ?? '';
    final pushedTechnicianId = data['technicianId']?.toString() ?? '';
    if (localTechnicianId.isNotEmpty &&
        pushedTechnicianId.isNotEmpty &&
        localTechnicianId != pushedTechnicianId) {
      return;
    }

    final technician = widget.session['technician'];
    if (technician is Map) {
      final updatedTechnician = Map<String, dynamic>.from(technician);
      final review = updatedTechnician['registrationReview'] is Map
          ? Map<String, dynamic>.from(
              updatedTechnician['registrationReview'] as Map)
          : <String, dynamic>{};

      review['status'] = status;
      review['note'] = data['note']?.toString() ?? review['note'] ?? '';
      review['reviewedAt'] =
          data['reviewedAt']?.toString() ?? review['reviewedAt'] ?? '';
      updatedTechnician['registrationReview'] = review;
      if (status == 'approved') {
        updatedTechnician['available'] = true;
      } else if (status == 'rejected') {
        updatedTechnician['available'] = false;
      }
      widget.session['technician'] = updatedTechnician;
    }

    final approved = status == 'approved';
    setState(() {
      if (approved) {
        _available = true;
        _registrationPayment ??= {
          'amount':
              int.tryParse(data['registrationFeeAmount']?.toString() ?? '') ??
                  5000,
          'currency': data['registrationFeeCurrency']?.toString() ?? 'TZS',
          'status':
              data['registrationPaymentStatus']?.toString() ?? 'not_requested',
          'actions': <dynamic>[],
        };
      } else {
        _available = false;
      }
      _status = data['body']?.toString() ??
          (approved
              ? l10n.registrationReviewApproved
              : status == 'rejected'
                  ? l10n.registrationReviewRejected
                  : l10n.registrationReviewPending);
    });

    if (approved) {
      _loadHistory();
      if (_locationSub == null) {
        _startLiveLocation();
      }
    } else if (!approved && _locationSub != null) {
      setState(() => _history = []);
      _locationSub?.cancel();
      _locationSub = null;
    } else if (!approved) {
      setState(() => _history = []);
    }
  }

  bool _isBlockedTechnicianRequestPush(String type) {
    return _isTechnician &&
        (!_technicianApproved || _clientRequestsBlocked) &&
        (type == 'tech_request' || type == 'service_request');
  }

  Future<void> _saveDeviceToken(String token) async {
    _deviceToken = token;
    try {
      await _api.updateDeviceToken(_user['id'].toString(), token);
    } catch (_) {
      // Location updates also carry the token, so this can be retried quietly.
    }
  }

  Widget _notificationsAction(AppLocalizations l10n) {
    final unreadLabel =
        _unreadNotifications > 99 ? '99+' : _unreadNotifications.toString();

    return IconButton(
      tooltip: l10n.notifications,
      onPressed: _showNotifications,
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.notifications_outlined),
          if (_unreadNotifications > 0)
            Positioned(
              top: -8,
              right: -8,
              child: Container(
                constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFFA62626),
                  borderRadius: BorderRadius.circular(999),
                ),
                alignment: Alignment.center,
                child: Text(
                  unreadLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _showNotifications() async {
    final l10n = AppLocalizations.of(context);
    setState(() => _unreadNotifications = 0);

    await showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      builder: (context) {
        final maxHeight = MediaQuery.sizeOf(context).height * .72;

        return SizedBox(
          height: maxHeight,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            child: Column(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCE4E8),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    const Icon(Icons.notifications_outlined),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l10n.notifications,
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                    IconButton(
                      tooltip: l10n.back,
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                if (_notifications.isEmpty)
                  Expanded(
                    child: Center(
                      child: Text(
                        l10n.noNotifications,
                        style: const TextStyle(color: Color(0xFF697781)),
                      ),
                    ),
                  )
                else
                  Expanded(
                    child: ListView.separated(
                      itemCount: _notifications.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final notification = _notifications[index];
                        return ListTile(
                          contentPadding:
                              const EdgeInsets.symmetric(vertical: 6),
                          leading: Icon(
                            _notificationIcon(notification.type),
                            color: const Color(0xFF0F766E),
                          ),
                          title: Text(
                            notification.title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          subtitle: Text(
                            notification.body,
                            maxLines: 4,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: Text(
                            notification.receivedAtLabel,
                            style: const TextStyle(
                              color: Color(0xFF697781),
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  IconData _notificationIcon(String type) {
    switch (type) {
      case 'tech_request':
      case 'service_request':
        return Icons.assignment_outlined;
      case 'request_response':
      case 'technician_response':
        return Icons.swap_horiz_outlined;
      case 'registration_review':
        return Icons.verified_user_outlined;
      case 'portal_warning':
        return Icons.warning_amber_outlined;
      case 'portal_news':
        return Icons.campaign_outlined;
      default:
        return Icons.notifications_outlined;
    }
  }

  Future<void> _startLiveLocation() async {
    if (_isTechnician && !_technicianApproved) {
      setState(() =>
          _status = AppLocalizations.of(context).registrationReviewPending);
      return;
    }

    final stream = await LocationService.getPositionStream();
    if (stream == null) {
      setState(() =>
          _status = AppLocalizations.of(context).locationTrackingRequired);
      return;
    }
    _locationSub = stream.listen((pos) async {
      try {
        if (_isTechnician && _technician != null) {
          final response = await _api.updateTechnicianLocation(
            _technician!['id'].toString(),
            pos.latitude,
            pos.longitude,
            _available,
            deviceToken: _deviceToken,
          );
          final technician = response['technician'];
          if (technician is Map) {
            widget.session['technician'] =
                Map<String, dynamic>.from(technician);
            if (_clientRequestsBlocked) {
              _available = false;
            }
          }
        } else {
          await _api.updateUserLocation(
            _user['id'].toString(),
            pos.latitude,
            pos.longitude,
            deviceToken: _deviceToken,
          );
        }
        if (mounted) {
          setState(
              () => _status = AppLocalizations.of(context).liveLocationUpdated);
        }
      } catch (e) {
        if (mounted) {
          if (e is ApiException && e.code == 'account_not_found') {
            await _locationSub?.cancel();
            _locationSub = null;
            setState(
                () => _status = AppLocalizations.of(context).sessionExpired);
            return;
          }

          setState(() =>
              _status = AppLocalizations.of(context).liveLocationUpdateFailed);
        }
      }
    }, onError: (Object error) {
      if (mounted) {
        setState(() =>
            _status = AppLocalizations.of(context).liveLocationUpdateFailed);
      }
    });
  }

  Future<void> _loadHistory() async {
    if (_isTechnician && !_technicianApproved) {
      if (mounted && _history.isNotEmpty) {
        setState(() => _history = []);
      }
      return;
    }

    try {
      final items = await _api.getHistory(
        clientId: _isTechnician ? null : _user['id'],
        technicianId:
            _isTechnician && _technician != null ? _technician!['id'] : null,
      );
      if (mounted) {
        setState(() => _history = items);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
      }
    }
  }

  void _onSearchChanged(String value) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _searchTechnicians);
  }

  Future<void> _searchTechnicians() async {
    final l10n = AppLocalizations.of(context);
    setState(() {
      _loading = true;
      _status = l10n.searchingTechnicians;
    });
    try {
      final pos = await LocationService.getCurrentPosition();
      if (pos == null) {
        throw Exception(l10n.locationRequired);
      }
      final data = await _api.searchTechnicians(
        skill: _searchCtrl.text.trim(),
        lat: pos.latitude,
        lon: pos.longitude,
        maxDistanceKm: _maxDistanceKm,
        available: _onlyAvailable,
        minRating: _minRating,
      );
      setState(() {
        _results = data;
        _status = data.isEmpty
            ? AppLocalizations.of(context).noTechMatch
            : AppLocalizations.of(context).foundTechnicians(data.length);
      });
    } catch (e) {
      setState(() {
        _results = [];
        _status = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _requestTechnician(Map<String, dynamic> tech) async {
    final l10n = AppLocalizations.of(context);
    setState(
        () => _status = l10n.sendingRequest(tech['name'] ?? l10n.technician));
    try {
      final pos = await LocationService.getCurrentPosition();
      if (pos == null) {
        throw Exception(l10n.locationRequired);
      }
      final response = await _api.requestTechnician({
        'clientId': _user['id'],
        'technicianId': tech['id'],
        'skill': _searchCtrl.text.trim(),
        'description': _descriptionCtrl.text.trim(),
        'lat': pos.latitude,
        'lon': pos.longitude,
      });
      setState(() => _status = response['technicianNotification'] is Map &&
              response['technicianNotification']['sent'] == true
          ? l10n.requestSent(tech['name'] ?? l10n.technician)
          : l10n.requestSavedNoPush);
      await _loadHistory();
    } catch (e) {
      setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _respond(Map<String, dynamic> request, String status) async {
    try {
      final l10n = AppLocalizations.of(context);
      final response = await _api.respondToRequest(request['id'], {
        'technicianId': _technician!['id'],
        'status': status,
        'message': status == 'accepted' ? l10n.technicianOnWay : '',
      });
      final notification = response['clientNotification'];
      setState(() => _status = notification is Map &&
              notification['required'] == true &&
              notification['sent'] != true
          ? l10n.clientNotificationNotDelivered
          : l10n.requestStatus(status));
      await _loadHistory();
    } catch (e) {
      setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _callTechnician(String phone) async {
    final digits = phone.replaceAll(RegExp(r'\s+'), '');
    if (digits.isEmpty) return;
    final uri = Uri(scheme: 'tel', path: digits);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _endRequest(Map<String, dynamic> request) async {
    final l10n = AppLocalizations.of(context);
    int rating = 5;
    final reportCtrl = TextEditingController();
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Padding(
              padding: EdgeInsets.fromLTRB(
                16,
                12,
                16,
                MediaQuery.viewInsetsOf(context).bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 44,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFDCE4E8),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    l10n.endRequest,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 14),
                  Text(l10n.rateTechnician),
                  const SizedBox(height: 8),
                  SegmentedButton<int>(
                    segments: List.generate(
                      5,
                      (index) => ButtonSegment<int>(
                        value: index + 1,
                        label: Text('${index + 1}'),
                      ),
                    ),
                    selected: {rating},
                    onSelectionChanged: (values) {
                      setDialogState(() => rating = values.first);
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: reportCtrl,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: l10n.reportTechnician,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(context).pop(false),
                          child: Text(l10n.back),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton(
                          onPressed: () => Navigator.of(context).pop(true),
                          child: Text(l10n.submit),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    if (submitted != true) {
      reportCtrl.dispose();
      return;
    }

    try {
      await _api.completeRequest(request['id'].toString(), {
        'clientId': _user['id'],
        'rating': rating,
        'report': reportCtrl.text.trim(),
      });
      reportCtrl.dispose();
      setState(() => _status = l10n.requestEnded);
      await _loadHistory();
    } catch (e) {
      reportCtrl.dispose();
      setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _reportAbuse(Map<String, dynamic> request) async {
    final l10n = AppLocalizations.of(context);
    final detailsCtrl = TextEditingController();
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) {
        return Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            12,
            16,
            MediaQuery.viewInsetsOf(context).bottom + 16,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCE4E8),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                l10n.reportAbuseTitle,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: detailsCtrl,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: l10n.reportAbuseDetails,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      child: Text(l10n.back),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: () => Navigator.of(context).pop(true),
                      child: Text(l10n.submit),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );

    if (submitted != true || detailsCtrl.text.trim().isEmpty) {
      detailsCtrl.dispose();
      return;
    }

    if (mounted) {
      setState(() {
        _loading = true;
        _status = l10n.submittingReport;
      });
    }

    try {
      await _api.reportRequest(request['id'].toString(), {
        'reporterRole': _isTechnician ? 'technician' : 'client',
        if (_isTechnician && _technician != null)
          'technicianId': _technician!['id'],
        if (!_isTechnician) 'clientId': _user['id'],
        'reason': 'abuse_or_misconduct',
        'details': detailsCtrl.text.trim(),
      });
      detailsCtrl.dispose();
      if (mounted) {
        setState(() => _status = l10n.reportSubmitted);
      }
    } catch (e) {
      detailsCtrl.dispose();
      if (mounted) {
        setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _toggleAvailability(bool value) async {
    if (_technician == null) return;
    final l10n = AppLocalizations.of(context);
    if (value && _clientRequestsBlocked) {
      setState(() {
        _available = false;
        _status = _clientRequestsBlockedReason.isNotEmpty
            ? _clientRequestsBlockedReason
            : l10n.clientRequestsBlockedBody;
      });
      return;
    }

    setState(() => _available = value);
    try {
      final response = await _api.updateAvailability(_technician!['id'], value);
      final technician = response['technician'];
      setState(() {
        if (technician is Map) {
          widget.session['technician'] = Map<String, dynamic>.from(technician);
          _available = _clientRequestsBlocked
              ? false
              : widget.session['technician']['available'] == true;
        }
        _status =
            value ? l10n.availableForRequests : l10n.unavailableForRequests;
      });
    } catch (e) {
      setState(() {
        _available =
            _technician?['available'] == true && !_clientRequestsBlocked;
        _status = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  Future<void> _refreshRegistrationPayment() async {
    if (_technician == null) return;
    final l10n = AppLocalizations.of(context);

    try {
      final response =
          await _api.refreshRegistrationPayment(_technician!['id']);
      final payment = response['registrationPayment'];
      if (mounted && payment is Map) {
        setState(() {
          _registrationPayment = Map<String, dynamic>.from(payment);
          _status = l10n.registrationPaymentStatus(
              _registrationPayment?['status']?.toString() ?? '');
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
      }
    }
  }

  Future<void> _sendRegistrationPaymentPush() async {
    if (_technician == null) return;
    final l10n = AppLocalizations.of(context);
    final payerPhone = _normalizedTanzaniaPhone(_paymentPhoneCtrl.text);

    if (!_isValidTanzaniaPhone(payerPhone)) {
      setState(() => _status = l10n.invalidPhone);
      return;
    }

    if (_looksLikeMpesa(payerPhone)) {
      setState(() => _status = l10n.mpesaNotSupported);
      return;
    }

    setState(() {
      _paymentLoading = true;
      _status = l10n.sendingPaymentPush;
    });

    try {
      final response = await _api.requestRegistrationPayment(
        _technician!['id'].toString(),
        payerPhone: payerPhone,
        operator: _selectedPaymentOperator,
      );
      final payment = response['registrationPayment'];
      if (mounted && payment is Map) {
        setState(() {
          _registrationPayment = Map<String, dynamic>.from(payment);
          _status = l10n.paymentPushSent;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _status = _messageForError(e, l10n));
      }
    } finally {
      if (mounted) {
        setState(() => _paymentLoading = false);
      }
    }
  }

  String _messageForError(Object error, AppLocalizations l10n) {
    if (error is ApiException && error.code == 'unsupported_payment_operator') {
      return l10n.mpesaNotSupported;
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  String _technicianNidaDisplay() {
    final formatted = _technician?['nidaFormatted']?.toString() ?? '';
    if (formatted.isNotEmpty) {
      return formatted;
    }

    return _formattedNida(_technician?['nida']);
  }

  String _formattedNida(Object? value) {
    final digits = value?.toString().replaceAll(RegExp(r'\D+'), '') ?? '';
    if (digits.length != 20) {
      return digits.isEmpty ? '-' : digits;
    }

    return '${digits.substring(0, 8)}-${digits.substring(8, 13)}-${digits.substring(13, 18)}-${digits.substring(18, 20)}';
  }

  String _normalizedTanzaniaPhone(Object? value) {
    final digits = value.toString().replaceAll(RegExp(r'\D+'), '');
    if (digits.startsWith('255')) return digits;
    if (digits.startsWith('0')) return '255${digits.substring(1)}';
    if (digits.length == 9) return '255$digits';
    return digits;
  }

  String _localTanzaniaPhone(Object? value) {
    final phone = _normalizedTanzaniaPhone(value);
    if (phone.startsWith('255') && phone.length == 12) {
      return '0${phone.substring(3)}';
    }
    return value?.toString() ?? '';
  }

  bool _isValidTanzaniaPhone(Object? value) {
    return RegExp(r'^255[67]\d{8}$').hasMatch(_normalizedTanzaniaPhone(value));
  }

  bool _looksLikeMpesa(Object? value) {
    final phone = _normalizedTanzaniaPhone(value);
    if (phone.length < 5 || !phone.startsWith('255')) return false;
    final prefix = phone.substring(3, 5);
    return const {'74', '75', '76'}.contains(prefix);
  }

  Widget _clientDashboard() {
    final l10n = AppLocalizations.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        _DashboardHeader(
          title: l10n.findTechnician,
          subtitle: l10n.clientDashboardSubtitle,
          icon: Icons.manage_search,
          trailing: _MetricPill(
            icon: Icons.history,
            label: l10n.requestsCount(_history.length),
          ),
        ),
        const SizedBox(height: 10),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    labelText: l10n.skill,
                    hintText: l10n.skillHint,
                    prefixIcon: Icon(Icons.search),
                  ),
                  onChanged: _onSearchChanged,
                  onSubmitted: (_) => _searchTechnicians(),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descriptionCtrl,
                  decoration: InputDecoration(
                    labelText: l10n.requestDetails,
                    prefixIcon: Icon(Icons.notes_outlined),
                  ),
                  minLines: 1,
                  maxLines: 3,
                ),
                const SizedBox(height: 12),
                _FilterSlider(
                  icon: Icons.route_outlined,
                  label: l10n.distance,
                  valueLabel: '${_maxDistanceKm.round()} km',
                  value: _maxDistanceKm,
                  min: 1,
                  max: 100,
                  divisions: 99,
                  onChanged: (v) => setState(() => _maxDistanceKm = v),
                  onChangeEnd: (_) => _searchTechnicians(),
                ),
                _FilterSlider(
                  icon: Icons.star_border,
                  label: l10n.minimumRating,
                  valueLabel: _minRating.toStringAsFixed(1),
                  value: _minRating,
                  min: 0,
                  max: 5,
                  divisions: 10,
                  onChanged: (v) => setState(() => _minRating = v),
                  onChangeEnd: (_) => _searchTechnicians(),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _onlyAvailable,
                  onChanged: (v) {
                    setState(() => _onlyAvailable = v);
                    _searchTechnicians();
                  },
                  title: Text(l10n.availableOnly),
                  secondary: const Icon(Icons.verified_outlined),
                ),
                const SizedBox(height: 6),
                FilledButton.icon(
                  onPressed: _loading ? null : _searchTechnicians,
                  icon: const Icon(Icons.manage_search),
                  label: Text(l10n.searchTechnicians),
                ),
              ],
            ),
          ),
        ),
        if (_loading)
          const Padding(
              padding: EdgeInsets.only(top: 8),
              child: LinearProgressIndicator()),
        if (_status.isNotEmpty) _InlineStatus(message: _status),
        const SizedBox(height: 10),
        _trackingSection(),
        const SizedBox(height: 10),
        _SectionTitle(
          title: l10n.matchingTechnicians,
          subtitle: _results.isEmpty
              ? l10n.noActiveSearch
              : l10n.techniciansFound(_results.length),
        ),
        ..._results
            .map((item) => _technicianCard(Map<String, dynamic>.from(item))),
        const SizedBox(height: 12),
        _historySection(),
      ],
    );
  }

  Widget _technicianDashboard() {
    final l10n = AppLocalizations.of(context);
    final pending = _history.where((r) => r['status'] == 'pending').toList();
    if (!_technicianApproved) {
      return ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          _DashboardHeader(
            title: l10n.technicianDashboard,
            subtitle: l10n.technicianDashboardSubtitle,
            icon: Icons.engineering,
          ),
          const SizedBox(height: 12),
          _registrationReviewCard(),
          if (_status.isNotEmpty) _InlineStatus(message: _status),
        ],
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        _DashboardHeader(
          title: l10n.technicianDashboard,
          subtitle: l10n.technicianDashboardSubtitle,
          icon: Icons.engineering,
          trailing: _MetricPill(
            icon: Icons.pending_actions,
            label: l10n.pendingCount(pending.length),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(8),
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.fingerprint_outlined),
                  title: Text(l10n.nida),
                  subtitle: Text(_technicianNidaDisplay()),
                ),
                if (_clientRequestsBlocked) ...[
                  const Padding(
                    padding: EdgeInsets.fromLTRB(8, 0, 8, 8),
                    child: Divider(),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                    child: _ReviewGuidanceBlock(
                      icon: Icons.block_outlined,
                      title: l10n.clientRequestsBlockedTitle,
                      body: _clientRequestsBlockedReason.isNotEmpty
                          ? _clientRequestsBlockedReason
                          : l10n.clientRequestsBlockedBody,
                      tone: _ReviewGuidanceTone.warning,
                    ),
                  ),
                ],
                SwitchListTile(
                  value: _available,
                  onChanged:
                      _clientRequestsBlocked ? null : _toggleAvailability,
                  title: Text(l10n.availableForNewRequests),
                  subtitle: Text(_clientRequestsBlocked
                      ? l10n.clientRequestsBlockedBody
                      : l10n.liveUpdatesOpen),
                  secondary: const Icon(Icons.location_on_outlined),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                  child: FilledButton.icon(
                    onPressed: _loadHistory,
                    icon: const Icon(Icons.refresh),
                    label: Text(l10n.refreshRequests),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (_registrationPayment != null) _registrationPaymentCard(),
        if (_loading)
          const Padding(
              padding: EdgeInsets.only(top: 8),
              child: LinearProgressIndicator()),
        if (_status.isNotEmpty) _InlineStatus(message: _status),
        const SizedBox(height: 12),
        _SectionTitle(
          title: l10n.newRequests,
          subtitle: pending.isEmpty
              ? l10n.noPendingWork
              : l10n.waitingForResponse(pending.length),
        ),
        if (pending.isEmpty) Text(l10n.noPendingRequests),
        ...pending.map((item) =>
            _requestCard(Map<String, dynamic>.from(item), actions: true)),
        const SizedBox(height: 12),
        _historySection(),
      ],
    );
  }

  Widget _registrationReviewCard() {
    final l10n = AppLocalizations.of(context);
    final status = _registrationReviewStatus;
    final rejected = status == 'rejected';
    final pending = status == 'pending';
    final title = rejected
        ? l10n.registrationReviewRejected
        : l10n.registrationReviewPending;
    final note = _registrationReview['note']?.toString() ?? '';
    final reviewTitle = rejected
        ? l10n.registrationReviewRejectedTitle
        : l10n.registrationReviewTitle;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: rejected
                    ? const Color(0xFFFDECEC)
                    : const Color(0xFFFFF7ED),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                rejected
                    ? Icons.report_gmailerrorred_outlined
                    : Icons.pending_actions_outlined,
                color: rejected
                    ? const Color(0xFFA62626)
                    : const Color(0xFFB45309),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    reviewTitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .titleSmall
                        ?.copyWith(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 4),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Chip(
                      avatar: Icon(
                        rejected
                            ? Icons.cancel_outlined
                            : Icons.hourglass_top_outlined,
                        size: 18,
                      ),
                      label: Text(l10n.registrationReviewStatus(status)),
                    ),
                  ),
                  const SizedBox(height: 6),
                  _ReviewGuidanceBlock(
                    icon: Icons.fingerprint_outlined,
                    title: l10n.nida,
                    body: _technicianNidaDisplay(),
                    tone: _ReviewGuidanceTone.info,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    title,
                    style: const TextStyle(color: Color(0xFF51616B)),
                  ),
                  if (rejected) ...[
                    const SizedBox(height: 8),
                    _ReviewGuidanceBlock(
                      icon: Icons.feedback_outlined,
                      title: l10n.registrationReviewReason,
                      body: note.isNotEmpty
                          ? note
                          : l10n.registrationReviewNoReason,
                      tone: _ReviewGuidanceTone.danger,
                    ),
                    const SizedBox(height: 8),
                    _ReviewGuidanceBlock(
                      icon: Icons.build_circle_outlined,
                      title: l10n.registrationReviewRectifyTitle,
                      body: l10n.registrationReviewRectify,
                      tone: _ReviewGuidanceTone.info,
                    ),
                    const SizedBox(height: 8),
                    _ReviewGuidanceBlock(
                      icon: Icons.visibility_off_outlined,
                      title: l10n.newRequests,
                      body: l10n.registrationReviewNoRequests,
                      tone: _ReviewGuidanceTone.warning,
                    ),
                  ] else if (pending) ...[
                    const SizedBox(height: 8),
                    _ReviewGuidanceBlock(
                      icon: Icons.visibility_off_outlined,
                      title: l10n.newRequests,
                      body: l10n.registrationReviewNoRequests,
                      tone: _ReviewGuidanceTone.warning,
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _registrationPaymentCard() {
    final l10n = AppLocalizations.of(context);
    final payment = _registrationPayment ?? {};
    final status = payment['status']?.toString() ?? '';
    final paid = status == 'success' || status == 'settled';
    final actions = payment['actions'] is List
        ? (payment['actions'] as List)
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList()
        : <Map<String, dynamic>>[];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: paid
                        ? const Color(0xFFE7F7F3)
                        : const Color(0xFFFFF7ED),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    paid
                        ? Icons.verified_outlined
                        : Icons.mobile_friendly_outlined,
                    color: paid
                        ? const Color(0xFF0F766E)
                        : const Color(0xFFB45309),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l10n.registrationFeeTitle,
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        l10n.registrationFeeLine(
                          payment['amount'] ?? 5000,
                          payment['currency'] ?? 'TZS',
                          status,
                        ),
                        style: const TextStyle(color: Color(0xFF697781)),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: l10n.refreshPaymentStatus,
                  onPressed:
                      _paymentLoading ? null : _refreshRegistrationPayment,
                  icon: const Icon(Icons.refresh),
                ),
              ],
            ),
            if (!paid) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.paymentPushTitle,
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _paymentPhoneCtrl,
                      enabled: !_paymentLoading,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        labelText: l10n.payingNumber,
                        hintText: '0712345678',
                        prefixIcon: const Icon(Icons.call_outlined),
                        helperText: l10n.payingNumberHint,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      l10n.paymentOperator,
                      style: Theme.of(context)
                          .textTheme
                          .labelLarge
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _paymentOperators
                          .map(
                            (operator) => ChoiceChip(
                              label: Text(operator),
                              selected: _selectedPaymentOperator == operator,
                              onSelected: _paymentLoading
                                  ? null
                                  : (_) => setState(
                                        () =>
                                            _selectedPaymentOperator = operator,
                                      ),
                              avatar: Icon(
                                _selectedPaymentOperator == operator
                                    ? Icons.check_circle
                                    : Icons.radio_button_unchecked,
                                size: 18,
                              ),
                            ),
                          )
                          .toList(),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        FilledButton.tonalIcon(
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(0, 38),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 10,
                            ),
                          ),
                          onPressed: _paymentLoading
                              ? null
                              : _sendRegistrationPaymentPush,
                          icon: const Icon(Icons.send_to_mobile_outlined),
                          label: Text(l10n.sendUssdPush),
                        ),
                        TextButton.icon(
                          onPressed: _paymentLoading
                              ? null
                              : _refreshRegistrationPayment,
                          icon: const Icon(Icons.sync, size: 18),
                          label: Text(l10n.trackPayment),
                        ),
                      ],
                    ),
                    if (_paymentLoading)
                      const Padding(
                        padding: EdgeInsets.only(top: 10),
                        child: LinearProgressIndicator(),
                      ),
                  ],
                ),
              ),
            ],
            if (actions.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                l10n.paymentActionsTitle,
                style: Theme.of(context)
                    .textTheme
                    .labelLarge
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              ...actions.map(_paymentActionTile),
            ],
          ],
        ),
      ),
    );
  }

  Widget _paymentActionTile(Map<String, dynamic> action) {
    final l10n = AppLocalizations.of(context);
    final status = action['status']?.toString() ?? '';
    final operator = action['operator']?.toString() ?? '';
    final phone = action['payerPhone']?.toString() ?? '';
    final color = _paymentStatusColor(status);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          Icon(_paymentStatusIcon(status), size: 20, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              l10n.paymentActionLine(operator, phone, status),
              style: const TextStyle(color: Color(0xFF30414A)),
            ),
          ),
          if ((action['requestedAt'] ?? '').toString().isNotEmpty)
            Text(
              l10n.tracked,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
        ],
      ),
    );
  }

  IconData _paymentStatusIcon(String status) {
    switch (status) {
      case 'success':
      case 'settled':
        return Icons.verified_outlined;
      case 'request_failed':
      case 'failed':
      case 'not_configured':
        return Icons.error_outline;
      case 'initiated':
      case 'processing':
      case 'pending':
        return Icons.hourglass_top_outlined;
      default:
        return Icons.receipt_long_outlined;
    }
  }

  Color _paymentStatusColor(String status) {
    switch (status) {
      case 'success':
      case 'settled':
        return const Color(0xFF0F766E);
      case 'request_failed':
      case 'failed':
      case 'not_configured':
        return const Color(0xFFB91C1C);
      default:
        return const Color(0xFFB45309);
    }
  }

  Widget _technicianCard(Map<String, dynamic> tech) {
    final l10n = AppLocalizations.of(context);
    final skills = tech['skills'] is List ? tech['skills'] as List : [];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: const Color(0xFFEEF5F3),
                  backgroundImage: (tech['image'] ?? '').toString().isNotEmpty
                      ? NetworkImage(tech['image'])
                      : null,
                  child: (tech['image'] ?? '').toString().isEmpty
                      ? const Icon(Icons.engineering, color: Color(0xFF0F766E))
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(tech['name'] ?? l10n.technician,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800)),
                        Text(
                            l10n.distanceAway(tech['distance'], tech['rating']),
                            style: const TextStyle(color: Color(0xFF697781))),
                      ]),
                ),
                FilledButton.icon(
                  onPressed: tech['available'] == true
                      ? () => _requestTechnician(tech)
                      : null,
                  icon: const Icon(Icons.send),
                  label: Text(l10n.request),
                ),
              ],
            ),
            if (skills.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: skills
                        .map((s) => Chip(label: Text(s.toString())))
                        .toList()),
              ),
          ],
        ),
      ),
    );
  }

  Widget _historySection() {
    final l10n = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionTitle(
          title: l10n.historyTitle,
          subtitle: l10n.historySubtitle,
        ),
        const SizedBox(height: 8),
        if (_history.isEmpty) Text(l10n.noPreviousRequests),
        ..._history
            .map((item) => _requestCard(Map<String, dynamic>.from(item))),
      ],
    );
  }

  Widget _trackingSection() {
    final l10n = AppLocalizations.of(context);
    final accepted = _history
        .where((item) => item is Map && item['status'] == 'accepted')
        .cast<Map>()
        .toList();

    if (_isTechnician || accepted.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionTitle(
          title: l10n.technicianTracking,
          subtitle: l10n.technicianTrackingSubtitle,
        ),
        ...accepted
            .map((item) => _trackingCard(Map<String, dynamic>.from(item))),
      ],
    );
  }

  Widget _trackingCard(Map<String, dynamic> request) {
    final l10n = AppLocalizations.of(context);
    final technician = request['technician'];
    final techMap =
        technician is Map ? Map<String, dynamic>.from(technician) : {};
    final location = techMap['location'];
    final techLocation = location is Map
        ? Map<String, dynamic>.from(location)
        : <String, dynamic>{};
    final clientLocation = request['clientLocation'];
    final clientMap = clientLocation is Map
        ? Map<String, dynamic>.from(clientLocation)
        : <String, dynamic>{};
    final distance = _distanceKm(
      _toDouble(clientMap['latitude']),
      _toDouble(clientMap['longitude']),
      _toDouble(techLocation['latitude']),
      _toDouble(techLocation['longitude']),
    );
    final initialDistance = _toDouble(request['distanceKm']);
    final routeProgress = _routeProgress(initialDistance, distance);
    final etaMinutes =
        distance == null ? null : math.max(1, (distance / 25 * 60).round());
    final phone = techMap['phone']?.toString() ?? '';
    final etaLabel =
        etaMinutes == null ? l10n.etaPending : l10n.etaMinutes(etaMinutes);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const _StatusIcon(icon: Icons.near_me, status: 'accepted'),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        techMap['name']?.toString() ?? l10n.technician,
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        distance == null
                            ? l10n.waitingForTechnicianLocation
                            : l10n.technicianDistance(distance),
                        style: const TextStyle(color: Color(0xFF697781)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 620;
                final map = _TrackingMap(
                  hasRoute: distance != null,
                  etaLabel: etaLabel,
                  progress: routeProgress,
                  height: wide ? 214 : 154,
                );
                final details = _TrackingDetails(
                  clientLabel: l10n.clientLocation,
                  clientValue: _formatLocation(clientMap),
                  technicianLabel: l10n.technicianLocation,
                  technicianValue: _formatLocation(techLocation),
                  phoneLabel: phone.isEmpty ? l10n.phoneUnavailable : phone,
                  onCall: phone.isEmpty ? null : () => _callTechnician(phone),
                  onEnd: () => _endRequest(request),
                  onReport: () => _reportAbuse(request),
                  endLabel: l10n.endRequest,
                  reportTooltip: l10n.reportAbuse,
                );

                if (!wide) {
                  return Column(
                    children: [
                      map,
                      const SizedBox(height: 10),
                      details,
                    ],
                  );
                }

                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(flex: 5, child: map),
                    const SizedBox(width: 12),
                    Expanded(flex: 4, child: details),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  double? _toDouble(Object? value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  double? _distanceKm(double? lat1, double? lon1, double? lat2, double? lon2) {
    if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) {
      return null;
    }

    final deltaLat = _toRad(lat2 - lat1);
    final deltaLon = _toRad(lon2 - lon1);
    final a = math.sin(deltaLat / 2) * math.sin(deltaLat / 2) +
        math.cos(_toRad(lat1)) *
            math.cos(_toRad(lat2)) *
            math.sin(deltaLon / 2) *
            math.sin(deltaLon / 2);

    return 6371 * 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));
  }

  double _toRad(double value) => value * math.pi / 180;

  double _routeProgress(double? initialDistance, double? remainingDistance) {
    if (initialDistance == null ||
        remainingDistance == null ||
        initialDistance <= 0) {
      return 0;
    }

    return (1 - (remainingDistance / initialDistance)).clamp(0.0, 1.0);
  }

  String _formatLocation(Map<String, dynamic> location) {
    final lat = _toDouble(location['latitude']);
    final lon = _toDouble(location['longitude']);
    if (lat == null || lon == null) {
      return AppLocalizations.of(context).locationPending;
    }

    return '${lat.toStringAsFixed(5)}, ${lon.toStringAsFixed(5)}';
  }

  Widget _requestCard(Map<String, dynamic> request, {bool actions = false}) {
    final l10n = AppLocalizations.of(context);
    final client = request['client'];
    final technician = request['technician'];
    final otherName = _isTechnician
        ? (client is Map ? client['name'] : l10n.client)
        : (technician is Map ? technician['name'] : l10n.technician);
    final status = request['status']?.toString() ?? 'pending';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            _StatusIcon(icon: _statusIcon(status), status: status),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$otherName | ${request['skill'] == '' ? l10n.generalService : request['skill']}',
                    style: Theme.of(context)
                        .textTheme
                        .titleSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    l10n.statusLine(status, request['distanceKm']),
                    style: const TextStyle(color: Color(0xFF697781)),
                  ),
                  if ((request['description'] ?? '').toString().isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(request['description'].toString()),
                    ),
                ],
              ),
            ),
            if (actions)
              Wrap(
                spacing: 4,
                children: [
                  IconButton.filledTonal(
                    tooltip: l10n.accept,
                    onPressed: () => _respond(request, 'accepted'),
                    icon: const Icon(Icons.check),
                  ),
                  IconButton(
                    tooltip: l10n.reject,
                    onPressed: () => _respond(request, 'rejected'),
                    icon: const Icon(Icons.close),
                  ),
                  IconButton(
                    tooltip: l10n.reportAbuse,
                    onPressed: () => _reportAbuse(request),
                    icon: const Icon(Icons.report_outlined),
                  ),
                ],
              ),
            if (!actions)
              IconButton(
                tooltip: l10n.reportAbuse,
                onPressed: () => _reportAbuse(request),
                icon: const Icon(Icons.report_outlined),
              ),
          ],
        ),
      ),
    );
  }

  IconData _statusIcon(String? status) {
    switch (status) {
      case 'accepted':
        return Icons.check_circle;
      case 'rejected':
        return Icons.cancel;
      case 'completed':
        return Icons.task_alt;
      case 'cancelled':
        return Icons.block;
      default:
        return Icons.pending_actions;
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _historyTimer?.cancel();
    _trackingTimer?.cancel();
    _locationSub?.cancel();
    _searchCtrl.dispose();
    _descriptionCtrl.dispose();
    _paymentPhoneCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(_isTechnician
            ? l10n.technicianDashboardTitle
            : l10n.findTechnicianTitle),
        actions: [
          _notificationsAction(l10n),
          _LanguageButton(
            locale: widget.locale,
            onLocaleChanged: widget.onLocaleChanged,
          ),
          IconButton(
            tooltip: l10n.logout,
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 860),
          child: _isTechnician ? _technicianDashboard() : _clientDashboard(),
        ),
      ),
    );
  }
}

class _AppNotification {
  final String type;
  final String title;
  final String body;
  final String receivedAtLabel;

  const _AppNotification({
    required this.type,
    required this.title,
    required this.body,
    required this.receivedAtLabel,
  });
}

enum _ReviewGuidanceTone { danger, warning, info }

class _ReviewGuidanceBlock extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;
  final _ReviewGuidanceTone tone;

  const _ReviewGuidanceBlock({
    required this.icon,
    required this.title,
    required this.body,
    required this.tone,
  });

  @override
  Widget build(BuildContext context) {
    final colors = switch (tone) {
      _ReviewGuidanceTone.danger => (
          background: const Color(0xFFFDECEC),
          foreground: const Color(0xFFA62626),
          border: const Color(0xFFF3B9B9),
        ),
      _ReviewGuidanceTone.warning => (
          background: const Color(0xFFFFF7ED),
          foreground: const Color(0xFFB45309),
          border: const Color(0xFFFCD34D),
        ),
      _ReviewGuidanceTone.info => (
          background: const Color(0xFFEEF5F3),
          foreground: const Color(0xFF0F766E),
          border: const Color(0xFFDCE4E8),
        ),
    };

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: colors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: colors.foreground, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: colors.foreground,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  style: const TextStyle(color: Color(0xFF30414A)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DashboardHeader extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final Widget? trailing;

  const _DashboardHeader({
    required this.title,
    required this.subtitle,
    required this.icon,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: Colors.white),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF17202A),
                      ),
                ),
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: const Color(0xFF697781),
                      ),
                ),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

class _MetricPill extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MetricPill({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFEEF5F3),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _FilterSlider extends StatelessWidget {
  final IconData icon;
  final String label;
  final String valueLabel;
  final double value;
  final double min;
  final double max;
  final int divisions;
  final ValueChanged<double> onChanged;
  final ValueChanged<double> onChangeEnd;

  const _FilterSlider({
    required this.icon,
    required this.label,
    required this.valueLabel,
    required this.value,
    required this.min,
    required this.max,
    required this.divisions,
    required this.onChanged,
    required this.onChangeEnd,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: const Color(0xFF697781)),
        const SizedBox(width: 12),
        SizedBox(
          width: 116,
          child:
              Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
        ),
        Expanded(
          child: Slider(
            value: value,
            min: min,
            max: max,
            divisions: divisions,
            label: valueLabel,
            onChanged: onChanged,
            onChangeEnd: onChangeEnd,
          ),
        ),
        SizedBox(
          width: 58,
          child: Text(valueLabel, textAlign: TextAlign.end),
        ),
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SectionTitle({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          Text(
            subtitle,
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: const Color(0xFF697781)),
          ),
        ],
      ),
    );
  }
}

class _InlineStatus extends StatelessWidget {
  final String message;

  const _InlineStatus({required this.message});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFFEEF5F3),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(message, style: const TextStyle(color: Color(0xFF0B5F59))),
      ),
    );
  }
}

class _StatusIcon extends StatelessWidget {
  final IconData icon;
  final String status;

  const _StatusIcon({required this.icon, required this.status});

  @override
  Widget build(BuildContext context) {
    final color = status == 'pending'
        ? const Color(0xFFB45309)
        : status == 'rejected' || status == 'cancelled'
            ? Theme.of(context).colorScheme.error
            : Theme.of(context).colorScheme.primary;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, color: color, size: 21),
    );
  }
}

class _TrackingMap extends StatelessWidget {
  final bool hasRoute;
  final String etaLabel;
  final double progress;
  final double height;

  const _TrackingMap({
    required this.hasRoute,
    required this.etaLabel,
    required this.progress,
    required this.height,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: CustomPaint(
        painter: _RouteMiniMapPainter(
          hasRoute: hasRoute,
          progress: progress,
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Align(
            alignment: Alignment.topRight,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFDCE4E8)),
              ),
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                child: Text(
                  etaLabel,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TrackingDetails extends StatelessWidget {
  final String clientLabel;
  final String clientValue;
  final String technicianLabel;
  final String technicianValue;
  final String phoneLabel;
  final VoidCallback? onCall;
  final VoidCallback onEnd;
  final VoidCallback onReport;
  final String endLabel;
  final String reportTooltip;

  const _TrackingDetails({
    required this.clientLabel,
    required this.clientValue,
    required this.technicianLabel,
    required this.technicianValue,
    required this.phoneLabel,
    required this.onCall,
    required this.onEnd,
    required this.onReport,
    required this.endLabel,
    required this.reportTooltip,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _LocationChip(
          icon: Icons.person_pin_circle_outlined,
          label: clientLabel,
          value: clientValue,
        ),
        const SizedBox(height: 8),
        _LocationChip(
          icon: Icons.engineering_outlined,
          label: technicianLabel,
          value: technicianValue,
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 330;
            final buttons = [
              FilledButton.icon(
                onPressed: onCall,
                icon: const Icon(Icons.call),
                label: Text(phoneLabel, overflow: TextOverflow.ellipsis),
              ),
              OutlinedButton.icon(
                onPressed: onEnd,
                icon: const Icon(Icons.task_alt),
                label: Text(endLabel, overflow: TextOverflow.ellipsis),
              ),
              IconButton.outlined(
                onPressed: onReport,
                tooltip: reportTooltip,
                icon: const Icon(Icons.report_outlined, size: 18),
                constraints:
                    const BoxConstraints.tightFor(width: 42, height: 42),
                padding: EdgeInsets.zero,
              ),
            ];

            if (compact) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final button in buttons) ...[
                    SizedBox(width: double.infinity, child: button),
                    if (button != buttons.last) const SizedBox(height: 8),
                  ],
                ],
              );
            }

            return Row(
              children: [
                Expanded(child: buttons[0]),
                const SizedBox(width: 8),
                Expanded(child: buttons[1]),
                const SizedBox(width: 8),
                buttons[2],
              ],
            );
          },
        ),
      ],
    );
  }
}

class _LocationChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _LocationChip({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F7F8),
        border: Border.all(color: const Color(0xFFDCE4E8)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(icon, size: 18, color: const Color(0xFF697781)),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF697781),
                        fontWeight: FontWeight.w700)),
                Text(
                  value,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RouteMiniMapPainter extends CustomPainter {
  final bool hasRoute;
  final double progress;

  const _RouteMiniMapPainter({
    required this.hasRoute,
    required this.progress,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final background = Paint()..color = const Color(0xFFE8F1EF);
    final road = Paint()
      ..color = const Color(0xFF9FB4BA)
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke;
    final route = Paint()
      ..color = hasRoute ? const Color(0xFF0F766E) : const Color(0xFF697781)
      ..strokeWidth = 4
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    final technicianMarker = Paint()..color = const Color(0xFF2563EB);
    final clientMarker = Paint()..color = const Color(0xFF0F766E);

    final rect = RRect.fromRectAndRadius(
      Offset.zero & size,
      const Radius.circular(8),
    );
    canvas.drawRRect(rect, background);

    for (var i = 1; i < 4; i++) {
      final y = size.height * i / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), road);
    }
    for (var i = 1; i < 4; i++) {
      final x = size.width * i / 4;
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), road);
    }

    final path = Path()
      ..moveTo(24, size.height - 24)
      ..cubicTo(size.width * .28, size.height * .62, size.width * .45,
          size.height * .78, size.width * .58, size.height * .45)
      ..cubicTo(size.width * .70, size.height * .18, size.width * .84,
          size.height * .28, size.width - 26, 24);
    canvas.drawPath(path, route);

    final metric = path.computeMetrics().first;
    final tangent =
        metric.getTangentForOffset(metric.length * progress.clamp(0.0, 1.0));
    final technicianPosition =
        tangent?.position ?? Offset(24, size.height - 24);

    canvas.drawCircle(Offset(size.width - 26, 24), 7, clientMarker);
    canvas.drawCircle(technicianPosition, 8, technicianMarker);
    canvas.drawCircle(technicianPosition, 3, Paint()..color = Colors.white);
  }

  @override
  bool shouldRepaint(covariant _RouteMiniMapPainter oldDelegate) {
    return oldDelegate.hasRoute != hasRoute || oldDelegate.progress != progress;
  }
}

class _LanguageButton extends StatelessWidget {
  final Locale locale;
  final ValueChanged<Locale> onLocaleChanged;

  const _LanguageButton({
    required this.locale,
    required this.onLocaleChanged,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return PopupMenuButton<Locale>(
      tooltip: l10n.language,
      icon: const Icon(Icons.language),
      onSelected: onLocaleChanged,
      itemBuilder: (context) => [
        PopupMenuItem(
          value: const Locale('en'),
          child: Row(
            children: [
              if (locale.languageCode == 'en')
                const Icon(Icons.check, size: 18),
              if (locale.languageCode == 'en') const SizedBox(width: 8),
              Text(l10n.english),
            ],
          ),
        ),
        PopupMenuItem(
          value: const Locale('sw'),
          child: Row(
            children: [
              if (locale.languageCode == 'sw')
                const Icon(Icons.check, size: 18),
              if (locale.languageCode == 'sw') const SizedBox(width: 8),
              Text(l10n.swahili),
            ],
          ),
        ),
      ],
    );
  }
}
