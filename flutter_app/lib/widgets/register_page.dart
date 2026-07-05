import 'dart:async';
import 'dart:convert';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';
import 'package:image_picker/image_picker.dart';
import '../l10n/app_localizations.dart';
import '../services/fcm_service.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

enum _RegistrationPhotoType { nidaCard, face }

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
  static const Duration _phoneCodeCooldown = Duration(seconds: 60);
  static const Duration _blockedPhoneCodeCooldown = Duration(minutes: 15);

  final FcmService _fcm = FcmService();
  final ApiService _api = ApiService();
  final ImagePicker _imagePicker = ImagePicker();
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      performanceMode: FaceDetectorMode.fast,
      enableClassification: false,
      enableContours: false,
      enableLandmarks: false,
      minFaceSize: 0.15,
    ),
  );
  final TextRecognizer _textRecognizer =
      TextRecognizer(script: TextRecognitionScript.latin);
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _nidaCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _skillsCtrl = TextEditingController();
  final _smsCodeCtrl = TextEditingController();
  final _emailCodeCtrl = TextEditingController();

  String _role = 'client';
  String _status = '';
  String _phoneVerificationMessage = '';
  String _emailVerificationMessage = '';
  String? _token;
  String? _phoneVerificationId;
  String? _phoneIdToken;
  String? _phoneVerificationProvider;
  String? _verifiedPhone;
  int? _phoneResendToken;
  String? _verifiedEmail;
  String? _verifiedEmailCode;
  String? _nidaCardImageData;
  String? _faceImageData;
  Uint8List? _nidaCardPreview;
  Uint8List? _facePreview;
  Timer? _phoneCooldownTimer;
  DateTime? _nextPhoneCodeAt;
  int _phoneCooldownSeconds = 0;
  bool _phoneCodeSent = false;
  bool _phoneVerified = false;
  bool _emailCodeSent = false;
  bool _emailVerified = false;
  bool _phoneVerificationLoading = false;
  bool _emailVerificationLoading = false;
  bool _phoneVerificationError = false;
  bool _emailVerificationError = false;
  bool _termsAccepted = false;
  bool _loading = false;
  bool _loginMode = false;

  @override
  void initState() {
    super.initState();
    _loginMode = widget.initialLoginMode;
    _role = widget.initialRole ?? _role;
    _status = widget.initialStatus ?? _status;
    _nidaCtrl.addListener(_handleNidaChanged);
    _phoneCtrl.addListener(_handlePhoneChanged);
    _emailCtrl.addListener(_handleEmailChanged);
    _initializeFcm();
  }

  void _handleNidaChanged() {
    final formatted = _formattedNida(_nidaCtrl.text);
    if (formatted == _nidaCtrl.text) {
      return;
    }

    _nidaCtrl.value = TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
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

  void _handlePhoneChanged() {
    final phone = _normalizedTanzaniaPhone(_phoneCtrl.text);
    if (!_phoneVerified && !_phoneCodeSent && _phoneIdToken == null) {
      return;
    }
    if (_phoneVerified && phone == _verifiedPhone) {
      return;
    }
    setState(() {
      _phoneVerified = false;
      _phoneCodeSent = false;
      _phoneVerificationId = null;
      _phoneIdToken = null;
      _phoneVerificationProvider = null;
      _verifiedPhone = null;
      _phoneResendToken = null;
      _phoneVerificationLoading = false;
      _phoneVerificationError = false;
      _phoneVerificationMessage = '';
      _smsCodeCtrl.clear();
    });
  }

  void _handleEmailChanged() {
    final email = _emailCtrl.text.trim().toLowerCase();
    if (!_emailVerified && !_emailCodeSent && _verifiedEmailCode == null) {
      return;
    }
    if (_emailVerified && email == _verifiedEmail) {
      return;
    }
    setState(() {
      _emailVerified = false;
      _emailCodeSent = false;
      _verifiedEmail = null;
      _verifiedEmailCode = null;
      _emailVerificationLoading = false;
      _emailVerificationError = false;
      _emailVerificationMessage = '';
      _emailCodeCtrl.clear();
    });
  }

  void _setRole(String role) {
    setState(() {
      _role = role;
      if (role != 'technician') {
        _nidaCtrl.clear();
        _skillsCtrl.clear();
        _nidaCardImageData = null;
        _faceImageData = null;
        _nidaCardPreview = null;
        _facePreview = null;
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final l10n = AppLocalizations.of(context);
    if (!_loginMode) {
      final phone = _normalizedTanzaniaPhone(_phoneCtrl.text);
      final email = _emailCtrl.text.trim().toLowerCase();
      if (_role == 'technician') {
        if (!_isValidNida(_nidaCtrl.text)) {
          setState(() => _status = l10n.invalidNida);
          return;
        }
        if (_nidaCardImageData == null || _faceImageData == null) {
          setState(() => _status = l10n.registrationImagesRequired);
          return;
        }
      }
      if (!_termsAccepted) {
        setState(() => _status = l10n.termsRequired);
        return;
      }
      if (!_phoneVerified || _phoneIdToken == null || _verifiedPhone != phone) {
        setState(() {
          _status = l10n.phoneVerificationRequired;
          _phoneVerificationMessage = l10n.phoneVerificationRequired;
          _phoneVerificationError = true;
        });
        return;
      }
      if (!_emailVerified || _verifiedEmail != email) {
        setState(() {
          _status = l10n.emailVerificationRequired;
          _emailVerificationMessage = l10n.emailVerificationRequired;
          _emailVerificationError = true;
        });
        return;
      }
    }

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
          if (_role == 'technician') 'nida': _normalizedNida(_nidaCtrl.text),
          if (_role == 'technician') 'nidaIdImage': _nidaCardImageData,
          if (_role == 'technician') 'faceImage': _faceImageData,
          'phone': _normalizedTanzaniaPhone(_phoneCtrl.text),
          'email': _emailCtrl.text.trim(),
          'emailVerificationCode':
              _verifiedEmailCode ?? _emailCodeCtrl.text.trim(),
          'password': _passwordCtrl.text,
          'phoneVerificationIdToken': _phoneIdToken,
          'phoneVerificationProvider': _phoneVerificationProvider,
          'termsAccepted': _termsAccepted,
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
    if (!_isValidEmail(email)) {
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

  Future<void> _sendEmailCode() async {
    final l10n = AppLocalizations.of(context);
    final email = _emailCtrl.text.trim().toLowerCase();
    if (!_isValidEmail(email)) {
      setState(() {
        _status = l10n.validEmail;
        _emailVerificationMessage = l10n.validEmail;
        _emailVerificationError = true;
      });
      return;
    }

    setState(() {
      _loading = true;
      _status = l10n.sendingEmailCode;
      _emailVerificationLoading = true;
      _emailVerificationError = false;
      _emailVerificationMessage = l10n.sendingEmailCode;
    });

    try {
      await _api.sendEmailVerification(email);
      if (mounted) {
        setState(() {
          _emailCodeSent = true;
          _emailVerified = false;
          _verifiedEmail = null;
          _verifiedEmailCode = null;
          _status = l10n.emailCodeSent;
          _emailVerificationLoading = false;
          _emailVerificationError = false;
          _emailVerificationMessage = l10n.emailCodeSent;
        });
      }
    } catch (e) {
      if (mounted) {
        final message = _messageForError(e, l10n);
        setState(() {
          _status = message;
          _emailVerificationLoading = false;
          _emailVerificationError = true;
          _emailVerificationMessage = message;
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _emailVerificationLoading = false;
        });
      }
    }
  }

  Future<void> _confirmEmailCode() async {
    final l10n = AppLocalizations.of(context);
    final email = _emailCtrl.text.trim().toLowerCase();
    final code = _emailCodeCtrl.text.trim();
    if (!_isValidEmail(email)) {
      setState(() {
        _status = l10n.validEmail;
        _emailVerificationMessage = l10n.validEmail;
        _emailVerificationError = true;
      });
      return;
    }
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() {
        _status = l10n.invalidVerificationCode;
        _emailVerificationMessage = l10n.invalidVerificationCode;
        _emailVerificationError = true;
      });
      return;
    }

    setState(() {
      _loading = true;
      _status = l10n.verifyEmail;
      _emailVerificationLoading = true;
      _emailVerificationError = false;
      _emailVerificationMessage = l10n.verifyEmail;
    });

    try {
      await _api.verifyEmail(email: email, code: code);
      if (mounted) {
        setState(() {
          _emailVerified = true;
          _verifiedEmail = email;
          _verifiedEmailCode = code;
          _status = l10n.emailVerified;
          _emailVerificationLoading = false;
          _emailVerificationError = false;
          _emailVerificationMessage = l10n.emailVerified;
        });
      }
    } catch (e) {
      if (mounted) {
        final message = _messageForError(e, l10n);
        setState(() {
          _status = message;
          _emailVerificationLoading = false;
          _emailVerificationError = true;
          _emailVerificationMessage = message;
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _emailVerificationLoading = false;
        });
      }
    }
  }

  Future<void> _sendPhoneCode() async {
    final l10n = AppLocalizations.of(context);
    final phone = _normalizedTanzaniaPhone(_phoneCtrl.text);
    if (!_isValidTanzaniaPhone(_phoneCtrl.text)) {
      setState(() {
        _status = l10n.invalidPhone;
        _phoneVerificationMessage = l10n.invalidPhone;
        _phoneVerificationError = true;
      });
      return;
    }
    if (_phoneCooldownSeconds > 0) {
      setState(() {
        _status = l10n.phoneCodeRetryIn(_phoneCooldownSeconds);
        _phoneVerificationMessage =
            l10n.phoneCodeRetryIn(_phoneCooldownSeconds);
        _phoneVerificationError = true;
      });
      return;
    }

    setState(() {
      _loading = true;
      _status = l10n.sendingPhoneCode;
      _phoneVerificationLoading = true;
      _phoneVerificationError = false;
      _phoneVerificationMessage = l10n.sendingPhoneCode;
      _phoneVerified = false;
      _phoneIdToken = null;
      _phoneVerificationProvider = null;
      _verifiedPhone = null;
    });
    _startPhoneCooldown(_phoneCodeCooldown);

    try {
      final providerResponse = await _api.phoneVerificationProvider();
      final provider =
          providerResponse['provider']?.toString().trim() ?? 'firebase';
      _phoneVerificationProvider = provider;

      if (provider != 'firebase') {
        await _sendBackendPhoneCode(phone, l10n);
        return;
      }

      await _preparePhoneAuthForSms();
      _logPhoneAuthStage('verifyPhoneNumber:start', phoneNumber: '+$phone');
      await FirebaseAuth.instance.verifyPhoneNumber(
        phoneNumber: '+$phone',
        timeout: const Duration(seconds: 60),
        forceResendingToken: _phoneCodeSent ? _phoneResendToken : null,
        verificationCompleted: (credential) async {
          _logPhoneAuthStage(
            'verificationCompleted',
            detail: 'smsCodePresent=${credential.smsCode != null}',
          );
          final smsCode = credential.smsCode;
          if (smsCode != null && smsCode.isNotEmpty && mounted) {
            setState(() {
              _smsCodeCtrl.text = smsCode;
              _status = l10n.smsCodeAutoRead;
              _phoneVerificationMessage = l10n.smsCodeAutoRead;
              _phoneVerificationError = false;
            });
          }
          await _completePhoneVerification(credential, l10n);
        },
        verificationFailed: (error) {
          _logPhoneAuthError('verificationFailed', error);
          if (_isPhoneAuthTemporarilyBlocked(error)) {
            _startPhoneCooldown(_blockedPhoneCodeCooldown);
          }
          if (mounted) {
            final message = _messageForError(error, l10n);
            setState(() {
              _loading = false;
              _phoneVerificationLoading = false;
              _phoneVerificationError = true;
              _phoneVerificationMessage = message;
              _status = message;
            });
          }
        },
        codeSent: (verificationId, resendToken) {
          _logPhoneAuthStage(
            'codeSent',
            detail: 'resendTokenPresent=${resendToken != null}',
          );
          if (mounted) {
            setState(() {
              _loading = false;
              _phoneVerificationId = verificationId;
              _phoneResendToken = resendToken;
              _phoneCodeSent = true;
              _status = l10n.phoneCodeSent;
              _phoneVerificationLoading = false;
              _phoneVerificationError = false;
              _phoneVerificationMessage = l10n.phoneCodeSent;
            });
          }
        },
        codeAutoRetrievalTimeout: (verificationId) {
          _logPhoneAuthStage('codeAutoRetrievalTimeout');
          if (mounted && !_phoneVerified) {
            setState(() => _phoneVerificationId = verificationId);
          }
        },
      );
    } catch (e) {
      _logPhoneAuthError('verifyPhoneNumber:catch', e);
      if (_isPhoneAuthTemporarilyBlocked(e)) {
        _startPhoneCooldown(_blockedPhoneCodeCooldown);
      }
      if (mounted) {
        final message = _messageForError(e, l10n);
        setState(() {
          _status = message;
          _phoneVerificationLoading = false;
          _phoneVerificationError = true;
          _phoneVerificationMessage = message;
        });
      }
    } finally {
      if (mounted && !_phoneCodeSent && !_phoneVerified) {
        setState(() {
          _loading = false;
          _phoneVerificationLoading = false;
        });
      }
    }
  }

  Future<void> _preparePhoneAuthForSms() async {
    await FirebaseAuth.instance.setLanguageCode(widget.locale.languageCode);
  }

  Future<void> _sendBackendPhoneCode(
    String phone,
    AppLocalizations l10n,
  ) async {
    final response = await _api.sendPhoneVerification(phone);
    final verificationId = response['verificationId']?.toString() ?? '';
    if (verificationId.isEmpty) {
      throw Exception(l10n.phoneVerificationFailed);
    }

    if (mounted) {
      final message = _phoneCodeSentMessage(response, l10n);
      setState(() {
        _loading = false;
        _phoneVerificationId = verificationId;
        _phoneCodeSent = true;
        _phoneResendToken = null;
        _status = message;
        _phoneVerificationLoading = false;
        _phoneVerificationError = false;
        _phoneVerificationMessage = message;
      });
    }
  }

  Future<void> _confirmPhoneCode() async {
    final l10n = AppLocalizations.of(context);
    final verificationId = _phoneVerificationId;
    final code = _smsCodeCtrl.text.trim();
    if (verificationId == null || verificationId.isEmpty) {
      setState(() {
        _status = l10n.phoneVerificationRequired;
        _phoneVerificationMessage = l10n.phoneVerificationRequired;
        _phoneVerificationError = true;
      });
      return;
    }
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() {
        _status = l10n.invalidVerificationCode;
        _phoneVerificationMessage = l10n.invalidVerificationCode;
        _phoneVerificationError = true;
      });
      return;
    }

    setState(() {
      _loading = true;
      _status = l10n.verifyPhone;
      _phoneVerificationLoading = true;
      _phoneVerificationError = false;
      _phoneVerificationMessage = l10n.verifyPhone;
    });

    if (_phoneVerificationProvider != null &&
        _phoneVerificationProvider != 'firebase') {
      await _completeBackendPhoneVerification(verificationId, code, l10n);
      return;
    }

    final credential = PhoneAuthProvider.credential(
      verificationId: verificationId,
      smsCode: code,
    );
    await _completePhoneVerification(credential, l10n);
  }

  Future<void> _completeBackendPhoneVerification(
    String verificationId,
    String code,
    AppLocalizations l10n,
  ) async {
    try {
      final phone = _normalizedTanzaniaPhone(_phoneCtrl.text);
      final response = await _api.verifyPhone(
        phone: phone,
        verificationId: verificationId,
        code: code,
      );
      final token = response['phoneVerificationIdToken']?.toString() ?? '';
      if (token.isEmpty) {
        throw Exception(l10n.phoneVerificationFailed);
      }

      if (mounted) {
        setState(() {
          _loading = false;
          _phoneVerified = true;
          _phoneCodeSent = true;
          _phoneIdToken = token;
          _phoneVerificationProvider =
              response['provider']?.toString() ?? _phoneVerificationProvider;
          _verifiedPhone = phone;
          _status = l10n.phoneVerified;
          _phoneVerificationLoading = false;
          _phoneVerificationError = false;
          _phoneVerificationMessage = l10n.phoneVerified;
        });
      }
    } catch (e) {
      if (mounted) {
        final message = _messageForError(e, l10n);
        setState(() {
          _loading = false;
          _phoneVerificationLoading = false;
          _phoneVerificationError = true;
          _phoneVerificationMessage = message;
          _status = message;
        });
      }
    }
  }

  Future<void> _completePhoneVerification(
    PhoneAuthCredential credential,
    AppLocalizations l10n,
  ) async {
    try {
      final result =
          await FirebaseAuth.instance.signInWithCredential(credential);
      final user = result.user;
      final token = await user?.getIdToken(true);
      final firebasePhone = _normalizedTanzaniaPhone(user?.phoneNumber ?? '');
      final enteredPhone = _normalizedTanzaniaPhone(_phoneCtrl.text);

      if (token == null || firebasePhone != enteredPhone) {
        throw FirebaseAuthException(
          code: 'phone-number-mismatch',
          message: l10n.phoneVerificationFailed,
        );
      }

      if (mounted) {
        setState(() {
          _loading = false;
          _phoneVerified = true;
          _phoneCodeSent = true;
          _phoneIdToken = token;
          _phoneVerificationProvider = 'firebase';
          _verifiedPhone = enteredPhone;
          _status = l10n.phoneVerified;
          _phoneVerificationLoading = false;
          _phoneVerificationError = false;
          _phoneVerificationMessage = l10n.phoneVerified;
        });
      }
    } catch (e) {
      _logPhoneAuthError('signInWithCredential', e);
      if (mounted) {
        final message = e is FirebaseAuthException
            ? (e.message ?? l10n.phoneVerificationFailed)
            : _messageForError(e, l10n);
        setState(() {
          _loading = false;
          _phoneVerificationLoading = false;
          _phoneVerificationError = true;
          _phoneVerificationMessage = message;
          _status = message;
        });
      }
    }
  }

  void _logPhoneAuthStage(
    String stage, {
    String? phoneNumber,
    String? detail,
  }) {
    final maskedPhone = phoneNumber?.replaceRange(
      4,
      phoneNumber.length > 4 ? phoneNumber.length : 4,
      '*******',
    );
    debugPrint(
      '[PhoneAuth] stage=$stage'
      '${maskedPhone == null ? '' : ' phone=$maskedPhone'}'
      '${detail == null ? '' : ' $detail'}',
    );
  }

  void _logPhoneAuthError(String stage, Object error) {
    if (error is FirebaseAuthException) {
      debugPrint(
        '[PhoneAuth] stage=$stage '
        'code=${error.code} plugin=${error.plugin} '
        'message=${error.message ?? '<empty>'} '
        'stack=${error.stackTrace ?? '<empty>'}',
      );
      return;
    }

    debugPrint('[PhoneAuth] stage=$stage error=$error');
  }

  Future<void> _captureRegistrationPhoto(_RegistrationPhotoType type) async {
    final l10n = AppLocalizations.of(context);
    setState(() => _status = l10n.capturingImage);

    try {
      final file = await _imagePicker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: type == _RegistrationPhotoType.face
            ? CameraDevice.front
            : CameraDevice.rear,
        imageQuality: 72,
        maxWidth: 1280,
        maxHeight: 1280,
      );
      if (file == null) {
        setState(() => _status = '');
        return;
      }

      if (type == _RegistrationPhotoType.face) {
        setState(() => _status = l10n.detectingFace);
        final faceCount = await _detectFaceCount(file.path);
        if (faceCount == 0) {
          setState(() => _status = l10n.faceNotDetected);
          return;
        }
        if (faceCount > 1) {
          setState(() => _status = l10n.multipleFacesDetected);
          return;
        }
      }

      String? detectedNida;
      if (type == _RegistrationPhotoType.nidaCard) {
        setState(() => _status = l10n.detectingNida);
        detectedNida = await _detectNidaNumber(file.path);
      }

      final bytes = await file.readAsBytes();
      final dataUri = 'data:image/jpeg;base64,${base64Encode(bytes)}';
      if (mounted) {
        setState(() {
          if (type == _RegistrationPhotoType.nidaCard) {
            _nidaCardImageData = dataUri;
            _nidaCardPreview = bytes;
            if (detectedNida != null) {
              _nidaCtrl.text = _formattedNida(detectedNida);
              _status = l10n.nidaAutoDetected;
            } else {
              _status = l10n.nidaImageCapturedNoAutoDetect;
            }
          } else {
            _faceImageData = dataUri;
            _facePreview = bytes;
            _status = l10n.faceImageCaptured;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _status = _messageForError(e, l10n));
      }
    }
  }

  Future<int> _detectFaceCount(String imagePath) async {
    if (kIsWeb) {
      return 1;
    }

    final faces =
        await _faceDetector.processImage(InputImage.fromFilePath(imagePath));
    return faces.length;
  }

  Future<String?> _detectNidaNumber(String imagePath) async {
    if (kIsWeb) {
      return null;
    }

    final recognizedText =
        await _textRecognizer.processImage(InputImage.fromFilePath(imagePath));
    final digits = recognizedText.text.replaceAll(RegExp(r'\D+'), '');
    final match = RegExp(r'\d{20}').firstMatch(digits);

    return match?.group(0);
  }

  String _phoneCodeSentMessage(
    Map<String, dynamic> response,
    AppLocalizations l10n,
  ) {
    final expiresIn = response['expiresInMinutes'];
    if (expiresIn is int && expiresIn > 0) {
      return l10n.phoneCodeSentWithExpiry(expiresIn);
    }
    if (expiresIn is num && expiresIn > 0) {
      return l10n.phoneCodeSentWithExpiry(expiresIn.toInt());
    }

    return l10n.phoneCodeSent;
  }

  String _messageForError(Object error, AppLocalizations l10n) {
    if (_loginMode &&
        error is ApiException &&
        error.code == 'invalid_credentials') {
      return l10n.invalidCredentials;
    }

    if (_isPhoneAuthTemporarilyBlocked(error)) {
      return l10n.phoneVerificationTemporarilyBlocked;
    }

    if (error is FirebaseAuthException) {
      final message = error.message;
      if (error.code == 'invalid-phone-number') {
        return l10n.invalidPhone;
      }
      if (error.code == 'invalid-verification-code' ||
          error.code == 'missing-verification-code' ||
          error.code == 'session-expired') {
        return l10n.invalidVerificationCode;
      }
      if (_isPhoneAuthAppIdentifierError(error)) {
        return l10n.phoneVerificationAppIdentifierError;
      }
      if (message != null && message.trim().isNotEmpty) {
        return message;
      }
      return l10n.phoneVerificationFailed;
    }

    if (error is ApiException && error.code == 'unsupported_payment_operator') {
      return l10n.mpesaNotSupported;
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  bool _isPhoneAuthTemporarilyBlocked(Object error) {
    if (error is! FirebaseAuthException) {
      return false;
    }

    final code = error.code.toLowerCase();
    final message = (error.message ?? '').toLowerCase();
    return code == 'too-many-requests' ||
        code == 'quota-exceeded' ||
        message.contains('blocked all requests') ||
        message.contains('unusual activity') ||
        message.contains('too many attempts') ||
        message.contains('quota');
  }

  bool _isPhoneAuthAppIdentifierError(FirebaseAuthException error) {
    final code = error.code.toLowerCase();
    final message = (error.message ?? '').toLowerCase();
    final compactMessage = message.replaceAll(RegExp(r'\s+'), '');
    final hasCode39 = compactMessage.contains('errorcode:39') ||
        compactMessage.contains('errorcode:-39') ||
        compactMessage.contains('code:39') ||
        compactMessage.contains('code:-39');

    return code == 'missing-client-identifier' ||
        code == 'app-not-authorized' ||
        code == 'invalid-app-credential' ||
        code == 'invalid-app-credentials' ||
        code == 'captcha-check-failed' ||
        ((code == 'internal-error' || code == 'unknown') && hasCode39) ||
        message.contains('app-not-authorized') ||
        message.contains('not authorized') ||
        message.contains('configuration_not_found') ||
        message.contains('missing client identifier') ||
        message.contains('valid app identifier') ||
        message.contains('play integrity') ||
        message.contains('recaptcha') ||
        message.contains('not a robot') ||
        message.contains('captcha');
  }

  void _startPhoneCooldown(Duration duration) {
    _phoneCooldownTimer?.cancel();
    _nextPhoneCodeAt = DateTime.now().add(duration);
    _updatePhoneCooldown();
    _phoneCooldownTimer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => _updatePhoneCooldown(),
    );
  }

  void _updatePhoneCooldown() {
    final nextPhoneCodeAt = _nextPhoneCodeAt;
    final remaining = nextPhoneCodeAt == null
        ? 0
        : nextPhoneCodeAt.difference(DateTime.now()).inSeconds + 1;
    final seconds = remaining > 0 ? remaining : 0;

    if (seconds == 0) {
      _phoneCooldownTimer?.cancel();
      _phoneCooldownTimer = null;
      _nextPhoneCodeAt = null;
    }

    if (mounted) {
      setState(() => _phoneCooldownSeconds = seconds);
    } else {
      _phoneCooldownSeconds = seconds;
    }
  }

  String _normalizedTanzaniaPhone(String value) {
    final digits = value.replaceAll(RegExp(r'\D+'), '');
    if (digits.startsWith('255')) return digits;
    if (digits.startsWith('0')) return '255${digits.substring(1)}';
    if (digits.length == 9) return '255$digits';
    return digits;
  }

  bool _isValidTanzaniaPhone(String value) {
    return RegExp(r'^255[67]\d{8}$').hasMatch(_normalizedTanzaniaPhone(value));
  }

  bool _isValidEmail(String value) {
    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value.trim());
  }

  String _normalizedNida(String value) {
    return value.replaceAll(RegExp(r'\D+'), '');
  }

  String _formattedNida(String value) {
    final digits = _normalizedNida(value);
    final limited = digits.length > 20 ? digits.substring(0, 20) : digits;
    final parts = <String>[];

    if (limited.isNotEmpty) {
      parts.add(limited.substring(0, limited.length < 8 ? limited.length : 8));
    }
    if (limited.length > 8) {
      parts
          .add(limited.substring(8, limited.length < 13 ? limited.length : 13));
    }
    if (limited.length > 13) {
      parts.add(
          limited.substring(13, limited.length < 18 ? limited.length : 18));
    }
    if (limited.length > 18) {
      parts.add(
          limited.substring(18, limited.length < 20 ? limited.length : 20));
    }

    return parts.join('-');
  }

  bool _isValidNida(String value) {
    return RegExp(r'^\d{20}$').hasMatch(_normalizedNida(value));
  }

  @override
  void dispose() {
    _phoneCooldownTimer?.cancel();
    _nidaCtrl.removeListener(_handleNidaChanged);
    _phoneCtrl.removeListener(_handlePhoneChanged);
    _emailCtrl.removeListener(_handleEmailChanged);
    _nameCtrl.dispose();
    _nidaCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _skillsCtrl.dispose();
    _smsCodeCtrl.dispose();
    _emailCodeCtrl.dispose();
    _faceDetector.close();
    _textRecognizer.close();
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
                                  : (value) => _setRole(value.first),
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
                                  if (v == null || v.trim().isEmpty) {
                                    return l10n.phoneRequired;
                                  }
                                  if (!_isValidTanzaniaPhone(v)) {
                                    return l10n.invalidPhone;
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 8),
                              _VerificationNotice(
                                  message: l10n.transactionPhoneNotice),
                              const SizedBox(height: 10),
                              _VerificationActionRow(
                                helper: l10n.smsAutoReadHint,
                                verified: _phoneVerified,
                                sent: _phoneCodeSent,
                                onPressed: _loading ||
                                        _phoneVerified ||
                                        _phoneCooldownSeconds > 0
                                    ? null
                                    : _sendPhoneCode,
                                label: Text(_phoneVerified
                                    ? l10n.phoneVerified
                                    : _phoneCooldownSeconds > 0
                                        ? l10n.phoneCodeRetryIn(
                                            _phoneCooldownSeconds)
                                        : _phoneCodeSent
                                            ? l10n.resendPhoneCode
                                            : l10n.sendPhoneCode),
                                icon: _phoneVerified
                                    ? Icons.verified_outlined
                                    : Icons.sms_outlined,
                              ),
                              if (_phoneCodeSent && !_phoneVerified) ...[
                                const SizedBox(height: 10),
                                TextFormField(
                                  controller: _smsCodeCtrl,
                                  decoration: const InputDecoration(
                                    prefixIcon: Icon(Icons.password_outlined),
                                  ).copyWith(labelText: l10n.phoneCode),
                                  keyboardType: TextInputType.number,
                                  validator: (v) {
                                    if (_loginMode || _phoneVerified) {
                                      return null;
                                    }
                                    if (v == null ||
                                        !RegExp(r'^\d{6}$')
                                            .hasMatch(v.trim())) {
                                      return l10n.invalidVerificationCode;
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 10),
                                _TinyActionButton(
                                  onPressed:
                                      _loading ? null : _confirmPhoneCode,
                                  icon: Icons.verified_user_outlined,
                                  label: l10n.verifyPhone,
                                ),
                              ],
                              _VerificationInlineFeedback(
                                loading: _phoneVerificationLoading,
                                message: _phoneVerificationMessage,
                                isError: _phoneVerificationError,
                                isSuccess: _phoneVerified,
                              ),
                              if (_role == 'technician') ...[
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _nidaCtrl,
                                  decoration: const InputDecoration(
                                    prefixIcon:
                                        Icon(Icons.fingerprint_outlined),
                                    counterText: '',
                                  ).copyWith(
                                    labelText: l10n.nida,
                                    hintText: 'XXXXXXXX-XXXXX-XXXXX-XX',
                                    helperText: l10n.nidaFormatHint,
                                  ),
                                  inputFormatters: [
                                    FilteringTextInputFormatter.allow(
                                        RegExp(r'[0-9-]')),
                                    LengthLimitingTextInputFormatter(23),
                                  ],
                                  keyboardType: TextInputType.number,
                                  maxLength: 23,
                                  validator: (v) {
                                    if (v == null || v.trim().isEmpty) {
                                      return l10n.nidaRequired;
                                    }
                                    if (!_isValidNida(v)) {
                                      return l10n.invalidNida;
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),
                                _CaptureCard(
                                  title: l10n.nidaCardPhoto,
                                  helper: l10n.nidaCardPhotoHint,
                                  icon: Icons.credit_card_outlined,
                                  imageBytes: _nidaCardPreview,
                                  onPressed: _loading
                                      ? null
                                      : () => _captureRegistrationPhoto(
                                          _RegistrationPhotoType.nidaCard),
                                  buttonLabel: _nidaCardPreview == null
                                      ? l10n.captureNidaCard
                                      : l10n.retakeNidaCard,
                                ),
                                const SizedBox(height: 12),
                                _CaptureCard(
                                  title: l10n.facePhoto,
                                  helper: l10n.facePhotoHint,
                                  icon: Icons.face_outlined,
                                  imageBytes: _facePreview,
                                  onPressed: _loading
                                      ? null
                                      : () => _captureRegistrationPhoto(
                                          _RegistrationPhotoType.face),
                                  buttonLabel: _facePreview == null
                                      ? l10n.captureFace
                                      : l10n.retakeFace,
                                  statusLabel: _facePreview == null
                                      ? l10n.faceDetectionOn
                                      : l10n.faceDetected,
                                ),
                                const SizedBox(height: 12),
                                _VerificationNotice(
                                    message: l10n.adminReviewNotice),
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
                              validator: (v) => v == null || !_isValidEmail(v)
                                  ? l10n.validEmail
                                  : null,
                            ),
                            if (!_loginMode) ...[
                              const SizedBox(height: 10),
                              _VerificationActionRow(
                                helper: l10n.enterVerificationCode,
                                verified: _emailVerified,
                                sent: _emailCodeSent,
                                onPressed: _loading ? null : _sendEmailCode,
                                label: Text(_emailVerified
                                    ? l10n.emailVerified
                                    : l10n.sendEmailCode),
                                icon: _emailVerified
                                    ? Icons.mark_email_read_outlined
                                    : Icons.outgoing_mail,
                              ),
                              if (_emailCodeSent && !_emailVerified) ...[
                                const SizedBox(height: 10),
                                TextFormField(
                                  controller: _emailCodeCtrl,
                                  decoration: const InputDecoration(
                                    prefixIcon: Icon(Icons.pin_outlined),
                                  ).copyWith(labelText: l10n.emailCode),
                                  keyboardType: TextInputType.number,
                                  validator: (v) {
                                    if (_loginMode || _emailVerified) {
                                      return null;
                                    }
                                    if (v == null ||
                                        !RegExp(r'^\d{6}$')
                                            .hasMatch(v.trim())) {
                                      return l10n.invalidVerificationCode;
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 10),
                                _TinyActionButton(
                                  onPressed:
                                      _loading ? null : _confirmEmailCode,
                                  icon: Icons.verified_outlined,
                                  label: l10n.verifyEmail,
                                ),
                              ],
                              _VerificationInlineFeedback(
                                loading: _emailVerificationLoading,
                                message: _emailVerificationMessage,
                                isError: _emailVerificationError,
                                isSuccess: _emailVerified,
                              ),
                            ],
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
                            if (!_loginMode) ...[
                              const SizedBox(height: 12),
                              CheckboxListTile(
                                value: _termsAccepted,
                                onChanged: _loading
                                    ? null
                                    : (value) => setState(
                                        () => _termsAccepted = value ?? false),
                                controlAffinity:
                                    ListTileControlAffinity.leading,
                                dense: true,
                                contentPadding: EdgeInsets.zero,
                                title: Text(l10n.acceptTerms),
                              ),
                            ],
                            const SizedBox(height: 18),
                            FilledButton.icon(
                              style: FilledButton.styleFrom(
                                minimumSize: const Size(0, 42),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 11,
                                ),
                              ),
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

class _VerificationInlineFeedback extends StatelessWidget {
  final bool loading;
  final String message;
  final bool isError;
  final bool isSuccess;

  const _VerificationInlineFeedback({
    required this.loading,
    required this.message,
    required this.isError,
    required this.isSuccess,
  });

  @override
  Widget build(BuildContext context) {
    if (!loading && message.trim().isEmpty) {
      return const SizedBox.shrink();
    }

    final color = Theme.of(context).colorScheme;
    final background = isError
        ? color.errorContainer
        : isSuccess
            ? const Color(0xFFE7F7F3)
            : const Color(0xFFF8FAFC);
    final foreground = isError
        ? color.onErrorContainer
        : isSuccess
            ? const Color(0xFF0F766E)
            : const Color(0xFF30414A);
    final borderColor = isError
        ? color.error.withValues(alpha: 0.25)
        : isSuccess
            ? const Color(0xFF99F6E4)
            : const Color(0xFFDCE4E8);

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: borderColor),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (loading) ...[
              LinearProgressIndicator(
                minHeight: 3,
                color: isError ? color.error : color.primary,
                backgroundColor: Colors.white,
              ),
              if (message.trim().isNotEmpty) const SizedBox(height: 8),
            ],
            if (message.trim().isNotEmpty)
              Text(
                message,
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: foreground),
              ),
          ],
        ),
      ),
    );
  }
}

class _VerificationNotice extends StatelessWidget {
  final String message;

  const _VerificationNotice({required this.message});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          Icons.info_outline,
          size: 18,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: const Color(0xFF51616B)),
          ),
        ),
      ],
    );
  }
}

class _VerificationActionRow extends StatelessWidget {
  final String helper;
  final bool verified;
  final bool sent;
  final VoidCallback? onPressed;
  final IconData icon;
  final Widget label;

  const _VerificationActionRow({
    required this.helper,
    required this.verified,
    required this.sent,
    required this.onPressed,
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme;
    final borderColor = verified
        ? const Color(0xFF99F6E4)
        : sent
            ? const Color(0xFFFCD34D)
            : const Color(0xFFDCE4E8);
    final background = verified
        ? const Color(0xFFE7F7F3)
        : sent
            ? const Color(0xFFFFFBEB)
            : const Color(0xFFF8FAFC);

    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final text = Row(
            children: [
              Icon(icon,
                  color: verified ? color.primary : const Color(0xFF475569)),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  helper,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: const Color(0xFF51616B)),
                ),
              ),
            ],
          );
          final action = _TinyActionButton(
            onPressed: onPressed,
            icon: icon,
            labelWidget: label,
          );

          if (constraints.maxWidth < 360) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                text,
                const SizedBox(height: 8),
                action,
              ],
            );
          }

          return Row(
            children: [
              Expanded(child: text),
              const SizedBox(width: 8),
              action,
            ],
          );
        },
      ),
    );
  }
}

class _CaptureCard extends StatelessWidget {
  final String title;
  final String helper;
  final String buttonLabel;
  final String? statusLabel;
  final IconData icon;
  final Uint8List? imageBytes;
  final VoidCallback? onPressed;

  const _CaptureCard({
    required this.title,
    required this.helper,
    required this.buttonLabel,
    this.statusLabel,
    required this.icon,
    required this.imageBytes,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFDCE4E8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF5F3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: color.primary),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      helper,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: const Color(0xFF51616B)),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _CaptureIconButton(
                onPressed: onPressed,
                tooltip: buttonLabel,
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (statusLabel != null) ...[
            _DetectionBadge(
              label: statusLabel!,
              detected: imageBytes != null,
            ),
            const SizedBox(height: 10),
          ],
          AspectRatio(
            aspectRatio: 16 / 9,
            child: Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                onTap: onPressed,
                borderRadius: BorderRadius.circular(8),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: imageBytes == null
                          ? const Color(0xFFDCE4E8)
                          : const Color(0xFF99F6E4),
                    ),
                  ),
                  child: imageBytes == null
                      ? Center(
                          child: Icon(
                            Icons.photo_camera_outlined,
                            color: color.primary,
                            size: 34,
                          ),
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(7),
                          child: Image.memory(imageBytes!, fit: BoxFit.cover),
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CaptureIconButton extends StatelessWidget {
  final VoidCallback? onPressed;
  final String tooltip;

  const _CaptureIconButton({
    required this.onPressed,
    required this.tooltip,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: IconButton.filledTonal(
        onPressed: onPressed,
        icon: const Icon(Icons.photo_camera_outlined),
        constraints: const BoxConstraints.tightFor(width: 44, height: 44),
      ),
    );
  }
}

class _DetectionBadge extends StatelessWidget {
  final String label;
  final bool detected;

  const _DetectionBadge({
    required this.label,
    required this.detected,
  });

  @override
  Widget build(BuildContext context) {
    final color = detected ? const Color(0xFF0F766E) : const Color(0xFF475569);
    final background =
        detected ? const Color(0xFFE7F7F3) : const Color(0xFFF1F5F9);
    final border = detected ? const Color(0xFF99F6E4) : const Color(0xFFDCE4E8);

    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              detected
                  ? Icons.verified_outlined
                  : Icons.face_retouching_natural,
              size: 16,
              color: color,
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: color, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

class _TinyActionButton extends StatelessWidget {
  final VoidCallback? onPressed;
  final IconData icon;
  final String? label;
  final Widget? labelWidget;

  const _TinyActionButton({
    required this.onPressed,
    required this.icon,
    this.label,
    this.labelWidget,
  });

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme;
    return TextButton.icon(
      style: TextButton.styleFrom(
        foregroundColor: color.primary,
        backgroundColor: Colors.white,
        minimumSize: const Size(0, 36),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
          side: const BorderSide(color: Color(0xFFDCE4E8)),
        ),
      ),
      onPressed: onPressed,
      icon: Icon(icon, size: 18),
      label: labelWidget ?? Text(label ?? ''),
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
