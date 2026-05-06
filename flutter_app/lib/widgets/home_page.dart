import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../l10n/app_localizations.dart';
import '../services/fcm_service.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class HomePage extends StatefulWidget {
  final Map<String, dynamic> session;
  final VoidCallback onLogout;
  final Locale locale;
  final ValueChanged<Locale> onLocaleChanged;

  const HomePage({
    required this.session,
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
  Timer? _debounce;
  Timer? _historyTimer;
  StreamSubscription<Position>? _locationSub;

  String _status = '';
  bool _loading = false;
  bool _available = true;
  bool _onlyAvailable = true;
  double _maxDistanceKm = 25;
  double _minRating = 0;
  List<dynamic> _results = [];
  List<dynamic> _history = [];

  Map<String, dynamic> get _user =>
      Map<String, dynamic>.from(widget.session['user'] as Map);
  Map<String, dynamic>? get _technician {
    final tech = widget.session['technician'];
    return tech == null ? null : Map<String, dynamic>.from(tech as Map);
  }

  bool get _isTechnician => _user['role'] == 'technician';

  @override
  void initState() {
    super.initState();
    _initializeForegroundUpdates();
    _loadHistory();
    _startLiveLocation();
  }

  Future<void> _initializeForegroundUpdates() async {
    await _fcm.init((data) {
      if (!mounted) return;
      final type = data['type']?.toString() ?? '';
      if (type == 'request_response' || type == 'tech_request') {
        final l10n = AppLocalizations.of(context);
        setState(() {
          _status = type == 'request_response'
              ? '${l10n.requestUpdated}: ${l10n.statusLabel(data['status']?.toString() ?? l10n.newStatus)}'
              : l10n.newRequestReceived;
        });
        _loadHistory();
      }
    });
    _historyTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      _loadHistory();
    });
  }

  Future<void> _startLiveLocation() async {
    final stream = await LocationService.getPositionStream();
    if (stream == null) {
      setState(() =>
          _status = AppLocalizations.of(context).locationTrackingRequired);
      return;
    }
    _locationSub = stream.listen((pos) async {
      try {
        if (_isTechnician && _technician != null) {
          await _api.updateTechnicianLocation(
              _technician!['id'], pos.latitude, pos.longitude, _available);
        } else {
          await _api.updateUserLocation(
              _user['id'], pos.latitude, pos.longitude);
        }
        if (mounted) {
          setState(
              () => _status = AppLocalizations.of(context).liveLocationUpdated);
        }
      } catch (e) {
        if (mounted) {
          setState(
              () => _status = e.toString().replaceFirst('Exception: ', ''));
        }
      }
    });
  }

  Future<void> _loadHistory() async {
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
      setState(() => _status = response['pushed'] == true
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
      await _api.respondToRequest(request['id'], {
        'technicianId': _technician!['id'],
        'status': status,
        'message': status == 'accepted' ? l10n.technicianOnWay : '',
      });
      setState(() => _status = l10n.requestStatus(status));
      await _loadHistory();
    } catch (e) {
      setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _toggleAvailability(bool value) async {
    if (_technician == null) return;
    final l10n = AppLocalizations.of(context);
    setState(() => _available = value);
    try {
      await _api.updateAvailability(_technician!['id'], value);
      setState(() => _status =
          value ? l10n.availableForRequests : l10n.unavailableForRequests);
    } catch (e) {
      setState(() => _status = e.toString().replaceFirst('Exception: ', ''));
    }
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
                SwitchListTile(
                  value: _available,
                  onChanged: _toggleAvailability,
                  title: Text(l10n.availableForNewRequests),
                  subtitle: Text(l10n.liveUpdatesOpen),
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
                ],
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
    _locationSub?.cancel();
    _searchCtrl.dispose();
    _descriptionCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          tooltip: l10n.back,
          onPressed: widget.onLogout,
          icon: const Icon(Icons.arrow_back),
        ),
        title: Text(_isTechnician
            ? l10n.technicianDashboardTitle
            : l10n.findTechnicianTitle),
        actions: [
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
