import 'package:flutter/material.dart';
import '../l10n/app_localizations.dart';
import '../services/fcm_service.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class RegisterPage extends StatefulWidget {
  final Function(Map<String, dynamic> session) onRegistered;
  final Locale locale;
  final ValueChanged<Locale> onLocaleChanged;
  final bool initialLoginMode;
  final String? initialRole;
  final String? initialStatus;

  const RegisterPage({
    required this.onRegistered,
    required this.locale,
    required this.onLocaleChanged,
    this.initialLoginMode = false,
    this.initialRole,
    this.initialStatus,
    super.key,
  });

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final FcmService _fcm = FcmService();
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _skillsCtrl = TextEditingController();

  String _role = 'client';
  String _status = '';
  String? _token;
  bool _loading = false;
  bool _loginMode = false;

  @override
  void initState() {
    super.initState();
    _loginMode = widget.initialLoginMode;
    _role = widget.initialRole ?? _role;
    _status = widget.initialStatus ?? _status;
    _initializeFcm();
  }

  Future<void> _initializeFcm() async {
    final token = await _fcm.init((data) {
      debugPrint('FCM data: $data');
    }, onTokenRefresh: (token) {
      if (mounted) {
        setState(() => _token = token);
      }
    });
    if (mounted) {
      setState(() => _token = token);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final l10n = AppLocalizations.of(context);
    setState(() {
      _loading = true;
      _status = _loginMode ? l10n.signingIn : l10n.gettingLocation;
    });

    try {
      Map<String, dynamic> session;
      if (_loginMode) {
        session = await _api.login({
          'email': _emailCtrl.text.trim(),
          'password': _passwordCtrl.text,
          'token': _token ?? '',
        });
      } else {
        final pos = await LocationService.getCurrentPosition();
        if (pos == null) {
          throw Exception(l10n.locationRequired);
        }
        session = await _api.registerDevice({
          'role': _role,
          'name': _nameCtrl.text.trim(),
          'phone': _normalizedTanzaniaPhone(_phoneCtrl.text),
          'email': _emailCtrl.text.trim(),
          'password': _passwordCtrl.text,
          'skills': _skillsCtrl.text,
          'token': _token ?? '',
          'lat': pos.latitude,
          'lon': pos.longitude,
        });
      }

      if (!mounted) {
        return;
      }
      widget.onRegistered(session);
    } catch (e) {
      if (mounted) {
        setState(() => _status = _messageForError(e, l10n));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _forgotPassword() async {
    final l10n = AppLocalizations.of(context);
    final email = _emailCtrl.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _status = l10n.validEmail);
      return;
    }

    setState(() {
      _loading = true;
      _status = l10n.sendingPasswordReset;
    });

    try {
      await _api.forgotPassword(email);
      if (mounted) {
        setState(() => _status = l10n.passwordResetSent);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _status = _messageForError(e, l10n));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  String _messageForError(Object error, AppLocalizations l10n) {
    if (_loginMode &&
        error is ApiException &&
        error.code == 'invalid_credentials') {
      return l10n.invalidCredentials;
    }

    if (error is ApiException && error.code == 'unsupported_payment_operator') {
      return l10n.mpesaNotSupported;
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  String _normalizedTanzaniaPhone(String value) {
    final digits = value.replaceAll(RegExp(r'\D+'), '');
    if (digits.startsWith('255')) return digits;
    if (digits.startsWith('0')) return '255${digits.substring(1)}';
    if (digits.length == 9) return '255$digits';
    return digits;
  }

  bool _looksLikeMpesa(String value) {
    final phone = _normalizedTanzaniaPhone(value);
    if (phone.length < 5 || !phone.startsWith('255')) return false;
    final prefix = phone.substring(3, 5);
    return const {'74', '75', '76'}.contains(prefix);
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _skillsCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final color = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.appName),
        actions: [
          _LanguageButton(
            locale: widget.locale,
            onLocaleChanged: widget.onLocaleChanged,
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: color.primary,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child:
                              const Icon(Icons.handyman, color: Colors.white),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _loginMode
                                    ? l10n.welcomeBack
                                    : l10n.createAccount,
                                style: Theme.of(context)
                                    .textTheme
                                    .headlineSmall
                                    ?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      color: const Color(0xFF17202A),
                                    ),
                              ),
                              Text(
                                _role == 'technician'
                                    ? l10n.technicianIntro
                                    : l10n.clientIntro,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium
                                    ?.copyWith(
                                      color: const Color(0xFF697781),
                                    ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            SegmentedButton<String>(
                              segments: [
                                ButtonSegment(
                                    value: 'client',
                                    icon: const Icon(Icons.person),
                                    label: Text(l10n.client)),
                                ButtonSegment(
                                    value: 'technician',
                                    icon: const Icon(Icons.engineering),
                                    label: Text(l10n.technician)),
                              ],
                              selected: {_role},
                              onSelectionChanged: _loginMode
                                  ? null
                                  : (value) =>
                                      setState(() => _role = value.first),
                            ),
                            const SizedBox(height: 16),
                            if (!_loginMode) ...[
                              TextFormField(
                                controller: _nameCtrl,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.badge_outlined),
                                ).copyWith(labelText: l10n.fullName),
                                validator: (v) => v == null || v.trim().isEmpty
                                    ? l10n.nameRequired
                                    : null,
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _phoneCtrl,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.call_outlined),
                                  prefixText: '+255 ',
                                ).copyWith(
                                  labelText: l10n.phone,
                                  hintText: '712345678',
                                ),
                                keyboardType: TextInputType.phone,
                                validator: (v) {
                                  if (_role != 'technician') return null;
                                  if (v == null || v.trim().isEmpty) {
                                    return l10n.phoneRequired;
                                  }
                                  if (_looksLikeMpesa(v)) {
                                    return l10n.mpesaNotSupported;
                                  }
                                  return null;
                                },
                              ),
                              if (_role == 'technician') ...[
                                const SizedBox(height: 12),
                                _RegistrationFeeNotice(l10n: l10n),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _skillsCtrl,
                                  decoration: const InputDecoration(
                                    prefixIcon:
                                        Icon(Icons.construction_outlined),
                                  ).copyWith(labelText: l10n.skillsComma),
                                  validator: (v) => _role == 'technician' &&
                                          (v == null || v.trim().isEmpty)
                                      ? l10n.addSkill
                                      : null,
                                ),
                              ],
                            ],
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _emailCtrl,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.email_outlined),
                              ).copyWith(labelText: l10n.email),
                              keyboardType: TextInputType.emailAddress,
                              validator: (v) => v == null || !v.contains('@')
                                  ? l10n.validEmail
                                  : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _passwordCtrl,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.lock_outline),
                              ).copyWith(labelText: l10n.password),
                              obscureText: true,
                              validator: (v) => v == null || v.length < 6
                                  ? l10n.minPassword
                                  : null,
                            ),
                            const SizedBox(height: 18),
                            FilledButton.icon(
                              onPressed: _loading ? null : _submit,
                              icon: Icon(_loginMode
                                  ? Icons.login
                                  : Icons.app_registration),
                              label: Text(_loginMode
                                  ? l10n.signIn
                                  : l10n.createAccount),
                            ),
                            const SizedBox(height: 6),
                            TextButton(
                              onPressed: _loading
                                  ? null
                                  : () =>
                                      setState(() => _loginMode = !_loginMode),
                              child: Text(_loginMode
                                  ? l10n.createNewAccount
                                  : l10n.alreadyRegistered),
                            ),
                            if (_loginMode)
                              TextButton(
                                onPressed: _loading ? null : _forgotPassword,
                                child: Text(l10n.forgotPassword),
                              ),
                          ],
                        ),
                      ),
                    ),
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.only(top: 12),
                        child: LinearProgressIndicator(),
                      ),
                    if (_status.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: _StatusBanner(message: _status),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _RegistrationFeeNotice extends StatelessWidget {
  final AppLocalizations l10n;

  const _RegistrationFeeNotice({required this.l10n});

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFEEF5F3),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFDCE4E8)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.payments_outlined, color: color.primary),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              l10n.registrationFeeNotice,
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium
                  ?.copyWith(color: const Color(0xFF30414A)),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBanner extends StatelessWidget {
  final String message;

  const _StatusBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.errorContainer,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
      ),
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
