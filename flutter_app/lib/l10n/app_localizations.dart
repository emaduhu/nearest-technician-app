import 'package:flutter/material.dart';

class AppLocalizations {
  final Locale locale;

  const AppLocalizations(this.locale);

  static const supportedLocales = [
    Locale('en'),
    Locale('sw'),
  ];

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  bool get isSwahili => locale.languageCode == 'sw';

  String get appName => isSwahili ? 'Fundi Karibu' : 'Nearest Technician';
  String get client => isSwahili ? 'Mteja' : 'Client';
  String get technician => isSwahili ? 'Fundi' : 'Technician';
  String get welcomeBack => isSwahili ? 'Karibu tena' : 'Welcome back';
  String get createAccount =>
      isSwahili ? 'Fungua akaunti' : 'Create your account';
  String get technicianIntro => isSwahili
      ? 'Dhibiti kazi, upatikanaji, na eneo la moja kwa moja.'
      : 'Manage jobs, availability, and live location.';
  String get clientIntro => isSwahili
      ? 'Pata mafundi waliothibitishwa karibu nawe.'
      : 'Find vetted technicians near your location.';
  String get fullName => isSwahili ? 'Jina kamili' : 'Full name';
  String get nida => isSwahili ? 'Namba ya NIDA' : 'NIDA number';
  String get nidaRequired =>
      isSwahili ? 'Namba ya NIDA inahitajika' : 'NIDA number is required';
  String get invalidNida => isSwahili
      ? 'Weka NIDA sahihi kwa muundo XXXXXXXX-XXXXX-XXXXX-XX'
      : 'Enter NIDA in the format XXXXXXXX-XXXXX-XXXXX-XX';
  String get nidaFormatHint => isSwahili
      ? 'Tarakimu 20; mistari itawekwa kiotomatiki.'
      : '20 digits; hyphens are added automatically.';
  String get nidaCardPhoto =>
      isSwahili ? 'Picha ya kitambulisho cha NIDA' : 'NIDA ID photo';
  String get nidaCardPhotoHint => isSwahili
      ? 'Piga picha ya kitambulisho chote kama kilivyo. Kona zote nne zionekane.'
      : 'Capture the full ID exactly as it is. All four corners must be visible.';
  String get facePhoto => isSwahili ? 'Picha ya uso' : 'Face photo';
  String get facePhotoHint => isSwahili
      ? 'Piga picha ya uso wako wazi kwa uhakiki wa msimamizi.'
      : 'Capture a clear face photo for admin review.';
  String get captureNidaCard =>
      isSwahili ? 'Piga picha ya NIDA' : 'Capture NIDA ID';
  String get retakeNidaCard =>
      isSwahili ? 'Rudia picha ya NIDA' : 'Retake NIDA ID';
  String get captureFace => isSwahili ? 'Piga picha ya uso' : 'Capture face';
  String get retakeFace => isSwahili ? 'Rudia picha ya uso' : 'Retake face';
  String get capturingImage =>
      isSwahili ? 'Inafungua kamera...' : 'Opening camera...';
  String get nidaImageCaptured =>
      isSwahili ? 'Picha ya NIDA imechukuliwa' : 'NIDA photo captured';
  String get faceImageCaptured =>
      isSwahili ? 'Picha ya uso imechukuliwa' : 'Face photo captured';
  String get registrationImagesRequired => isSwahili
      ? 'Picha ya NIDA na picha ya uso zinahitajika'
      : 'NIDA ID photo and face photo are required';
  String get adminReviewNotice => isSwahili
      ? 'Baada ya usajili, msimamizi atahakiki taarifa zako kabla ya kuendelea.'
      : 'After registration, an admin will review your details before you proceed.';
  String get registrationReviewTitle =>
      isSwahili ? 'Usajili unasubiri uhakiki' : 'Registration under review';
  String get registrationReviewRejectedTitle =>
      isSwahili ? 'Usajili umekataliwa' : 'Registration rejected';
  String registrationReviewStatus(String status) => isSwahili
      ? 'Hali: ${registrationReviewStatusLabel(status)}'
      : 'Status: ${registrationReviewStatusLabel(status)}';
  String registrationReviewStatusLabel(String status) {
    switch (status) {
      case 'approved':
        return isSwahili ? 'umeidhinishwa' : 'approved';
      case 'rejected':
        return isSwahili ? 'umekataliwa' : 'rejected';
      default:
        return isSwahili ? 'unasubiri' : 'pending';
    }
  }

  String get registrationReviewPending => isSwahili
      ? 'Msimamizi anakagua NIDA na picha yako. Utaendelea baada ya kuidhinishwa.'
      : 'An admin is reviewing your NIDA ID and face photo. You can proceed after approval.';
  String get registrationReviewRejected => isSwahili
      ? 'Usajili wako umekataliwa. Wasiliana na msaada au sajili tena kwa taarifa sahihi.'
      : 'Your registration was rejected. Contact support or register again with correct details.';
  String get registrationReviewReason =>
      isSwahili ? 'Sababu ya kukataliwa' : 'Rejection reason';
  String get registrationReviewNoReason => isSwahili
      ? 'Hakuna sababu iliyoandikwa. Wasiliana na msaada kwa maelezo.'
      : 'No reason was provided. Contact support for details.';
  String get registrationReviewRectifyTitle =>
      isSwahili ? 'Jinsi ya kurekebisha' : 'How to rectify';
  String get registrationReviewRectify => isSwahili
      ? 'Rekebisha taarifa au picha zilizoelezwa kwenye sababu, kisha sajili tena au wasiliana na msaada ili kufanyiwa uhakiki upya.'
      : 'Correct the details or photos mentioned in the reason, then register again or contact support so an admin can review you again.';
  String get registrationReviewNoRequests => isSwahili
      ? 'Hutaonyeshwa maombi ya wateja hadi usajili utakapoidhinishwa.'
      : 'Client requests will not be shown until your registration is approved.';
  String get registrationReviewApproved =>
      isSwahili ? 'Usajili umeidhinishwa' : 'Registration approved';
  String get clientRequestsBlockedTitle =>
      isSwahili ? 'Maombi ya wateja yamezuiwa' : 'Client requests blocked';
  String get clientRequestsBlockedBody => isSwahili
      ? 'Msimamizi amekuzuia kupokea maombi mapya ya wateja.'
      : 'Admin has blocked this technician from receiving new client requests.';
  String get notifications => isSwahili ? 'Arifa' : 'Notifications';
  String get noNotifications =>
      isSwahili ? 'Hakuna arifa bado' : 'No notifications yet';
  String get notificationReceived =>
      isSwahili ? 'Arifa imepokelewa' : 'Notification received';
  String get accountWarning =>
      isSwahili ? 'Onyo la akaunti' : 'Account warning';
  String get news => isSwahili ? 'Habari' : 'News';
  String get phone => isSwahili ? 'Simu' : 'Phone';
  String get phoneRequired =>
      isSwahili ? 'Namba ya simu inahitajika' : 'Phone number is required';
  String get mpesaNotSupported => isSwahili
      ? 'M-Pesa bado haipokelewi. Tumia namba ya Yas, Airtel au Halopesa.'
      : 'M-Pesa is not supported yet. Use a Yas, Airtel, or Halopesa number.';
  String get registrationFeeNotice => isSwahili
      ? 'Ada ya usajili itaonekana baada ya kuingia. Malipo kwa sasa yanapokelewa kupitia Yas, Airtel na Halopesa.'
      : 'The registration fee appears after login. Payments currently work with Yas, Airtel, and Halopesa.';
  String get registrationFeeOperators => isSwahili
      ? 'Tumia Yas, Airtel au Halopesa. Ombi likionyesha CLICKPESA, endelea. M-Pesa itaongezwa hivi karibuni.'
      : 'Use Yas, Airtel, or Halopesa. If the prompt shows CLICKPESA, proceed. M-Pesa is coming soon.';
  String get paymentPushTitle =>
      isSwahili ? 'Tuma ombi la USSD' : 'Send USSD payment push';
  String get payingNumber => isSwahili ? 'Namba itakayolipa' : 'Paying number';
  String get payingNumberHint => isSwahili
      ? 'Weka namba ya Yas, Airtel au Halopesa'
      : 'Enter a Yas, Airtel, or Halopesa number';
  String get paymentOperator => isSwahili ? 'Mtandao' : 'Operator';
  String get sendUssdPush => isSwahili ? 'Tuma USSD' : 'Send USSD';
  String get trackPayment => isSwahili ? 'Fuatilia' : 'Track';
  String get paymentActionsTitle =>
      isSwahili ? 'Majaribio yaliyofuatiliwa' : 'Tracked payment attempts';
  String get sendingPaymentPush =>
      isSwahili ? 'Inatuma ombi la malipo...' : 'Sending payment push...';
  String get paymentPushSent => isSwahili
      ? 'Ombi la malipo limetumwa. Angalia simu ya mlipaji.'
      : 'Payment push sent. Check the payer phone.';
  String get tracked => isSwahili ? 'imefuatiliwa' : 'tracked';
  String paymentActionLine(String operator, String phone, String status) => isSwahili
      ? '${operator.isEmpty ? '-' : operator} | ${phone.isEmpty ? '-' : phone} | ${registrationPaymentStatus(status)}'
      : '${operator.isEmpty ? '-' : operator} | ${phone.isEmpty ? '-' : phone} | ${registrationPaymentStatus(status)}';
  String get skillsComma =>
      isSwahili ? 'Ujuzi, tenganisha kwa koma' : 'Skills, comma separated';
  String get email => isSwahili ? 'Barua pepe' : 'Email';
  String get password => isSwahili ? 'Nenosiri' : 'Password';
  String get invalidCredentials => isSwahili
      ? 'Barua pepe au nenosiri si sahihi'
      : 'Email or password is incorrect';
  String get forgotPassword =>
      isSwahili ? 'Umesahau nenosiri?' : 'Forgot password?';
  String get sendingPasswordReset => isSwahili
      ? 'Inatuma kiungo cha kuweka upya nenosiri...'
      : 'Sending password reset link...';
  String get passwordResetSent => isSwahili
      ? 'Ikiwa akaunti ipo, kiungo cha kuweka upya nenosiri kimetumwa.'
      : 'If an account exists, a password reset link has been sent.';
  String get signIn => isSwahili ? 'Ingia' : 'Sign in';
  String get createNewAccount =>
      isSwahili ? 'Fungua akaunti mpya' : 'Create a new account';
  String get alreadyRegistered =>
      isSwahili ? 'Tayari umesajiliwa? Ingia' : 'Already registered? Sign in';
  String get nameRequired =>
      isSwahili ? 'Jina linahitajika' : 'Name is required';
  String get addSkill =>
      isSwahili ? 'Ongeza angalau ujuzi mmoja' : 'Add at least one skill';
  String get validEmail =>
      isSwahili ? 'Barua pepe sahihi inahitajika' : 'Valid email is required';
  String get invalidPhone => isSwahili
      ? 'Weka namba sahihi ya Tanzania'
      : 'Enter a valid Tanzania mobile number';
  String get transactionPhoneNotice => isSwahili
      ? 'Namba utakayothibitisha itatumika kwa miamala. Hutaweza kuibadilisha baada ya kuthibitisha.'
      : 'The phone number you verify will be used for transactions. You cannot change it after verification.';
  String get sendPhoneCode =>
      isSwahili ? 'Tuma msimbo wa simu' : 'Send phone code';
  String get resendPhoneCode =>
      isSwahili ? 'Tuma tena msimbo' : 'Resend phone code';
  String get verifyPhone => isSwahili ? 'Thibitisha simu' : 'Verify phone';
  String get phoneCode => isSwahili ? 'Msimbo wa simu' : 'Phone code';
  String get sendingPhoneCode =>
      isSwahili ? 'Inatuma msimbo wa simu...' : 'Sending phone code...';
  String get phoneCodeSent => isSwahili
      ? 'Msimbo umetumwa kwa SMS kwenye simu yako'
      : 'A code has been sent by SMS to your phone';
  String get smsAutoReadHint => isSwahili
      ? 'Tukipata SMS kiotomatiki, msimbo utajazwa bila kubonyeza kitu.'
      : 'If Android reads the SMS automatically, the code fills itself.';
  String get smsCodeAutoRead => isSwahili
      ? 'Msimbo wa SMS umejazwa kiotomatiki'
      : 'SMS code filled automatically';
  String get phoneVerified =>
      isSwahili ? 'Simu imethibitishwa' : 'Phone verified';
  String get phoneVerificationRequired => isSwahili
      ? 'Thibitisha namba ya simu kabla ya kuendelea'
      : 'Verify your phone number before continuing';
  String get phoneVerificationFailed => isSwahili
      ? 'Uthibitishaji wa simu umeshindikana'
      : 'Phone verification failed';
  String get phoneVerificationTemporarilyBlocked => isSwahili
      ? 'Firebase imezuia kwa muda maombi ya SMS kutoka kwenye kifaa au namba hii. Subiri kabla ya kujaribu tena, au jaribu namba halisi nyingine ambayo haijawekwa kama namba ya majaribio.'
      : 'Firebase has temporarily blocked SMS requests from this device or phone number. Wait before trying again, or try another real phone number that is not configured as a test number.';
  String get phoneVerificationAppIdentifierError => isSwahili
      ? 'Firebase imeshindwa kuthibitisha app hii kwa SMS (Error 39). Sakinisha APK mpya ya production iliyosainiwa na release key iliyosajiliwa. Kwa APK iliyosakinishwa nje ya Play Store, Firebase hutumia reCAPTCHA; hakikisha Chrome na Google Play services zimesasishwa.'
      : 'Firebase could not verify this app for SMS (Error 39). Install the latest production APK signed with the registered release key. For sideloaded APKs, Firebase uses reCAPTCHA; keep Chrome and Google Play services updated.';
  String phoneCodeRetryIn(int seconds) {
    final safeSeconds = seconds < 0 ? 0 : seconds;
    final minutes = safeSeconds ~/ 60;
    final remainingSeconds = safeSeconds % 60;
    final time = minutes > 0
        ? '${minutes}m ${remainingSeconds.toString().padLeft(2, '0')}s'
        : '${remainingSeconds}s';
    return isSwahili ? 'Jaribu tena baada ya $time' : 'Try again in $time';
  }

  String get sendEmailCode =>
      isSwahili ? 'Tuma msimbo wa barua pepe' : 'Send email code';
  String get verifyEmail =>
      isSwahili ? 'Thibitisha barua pepe' : 'Verify email';
  String get emailCode => isSwahili ? 'Msimbo wa barua pepe' : 'Email code';
  String get sendingEmailCode =>
      isSwahili ? 'Inatuma msimbo wa barua pepe...' : 'Sending email code...';
  String get emailCodeSent => isSwahili
      ? 'Msimbo umetumwa kwenye barua pepe'
      : 'A code has been sent to your email';
  String get emailVerified =>
      isSwahili ? 'Barua pepe imethibitishwa' : 'Email verified';
  String get emailVerificationRequired => isSwahili
      ? 'Thibitisha barua pepe kabla ya kuendelea'
      : 'Verify your email before continuing';
  String get invalidVerificationCode => isSwahili
      ? 'Weka msimbo wa tarakimu 6'
      : 'Enter the 6-digit verification code';
  String get enterVerificationCode => isSwahili
      ? 'Weka msimbo wa tarakimu 6 uliotumwa kwako.'
      : 'Enter the 6-digit code sent to you.';
  String get minPassword =>
      isSwahili ? 'Angalau herufi 6' : 'Minimum 6 characters';
  String get signingIn => isSwahili ? 'Inaingia...' : 'Signing in...';
  String get gettingLocation =>
      isSwahili ? 'Inapata eneo...' : 'Getting location...';
  String get locationRequired => isSwahili
      ? 'Ruhusa ya eneo inahitajika'
      : 'Location permission is required';
  String get locationTrackingRequired => isSwahili
      ? 'Ruhusa ya eneo inahitajika kwa ufuatiliaji wa moja kwa moja'
      : 'Location permission is required for live tracking';
  String get requestUpdated =>
      isSwahili ? 'Ombi limesasishwa' : 'Request updated';
  String get newStatus => isSwahili ? 'hali mpya' : 'new status';
  String get newRequestReceived =>
      isSwahili ? 'Ombi jipya limepokelewa' : 'New request received';
  String get liveLocationUpdated => isSwahili
      ? 'Eneo la moja kwa moja limesasishwa'
      : 'Live location updated';
  String get sessionExpired => isSwahili
      ? 'Muda wa akaunti umeisha. Tafadhali ingia tena.'
      : 'Your session is no longer valid. Please sign in again.';
  String get liveLocationUpdateFailed =>
      isSwahili ? 'Tatizo la muunganisho' : 'Connection failure';
  String get searchingTechnicians =>
      isSwahili ? 'Inatafuta mafundi...' : 'Searching technicians...';
  String get noTechMatch => isSwahili
      ? 'Hakuna fundi anayelingana na vichujio hivi'
      : 'No technicians match these filters';
  String foundTechnicians(int count) =>
      isSwahili ? 'Imepata mafundi $count' : 'Found $count technicians';
  String sendingRequest(String name) =>
      isSwahili ? 'Inatuma ombi kwa $name...' : 'Sending request to $name...';
  String requestSent(String name) =>
      isSwahili ? 'Ombi limetumwa kwa $name' : 'Request sent to $name';
  String get requestSavedNoPush => isSwahili
      ? 'Ombi limehifadhiwa. Arifa haikufika.'
      : 'Request saved. Push notification was not delivered.';
  String get clientNotificationNotDelivered => isSwahili
      ? 'Jibu limehifadhiwa. Arifa kwa mteja haikufika.'
      : 'Response saved. Client notification was not delivered.';
  String get technicianOnWay =>
      isSwahili ? 'Fundi yuko njiani' : 'Technician is on the way';
  String requestStatus(String status) => isSwahili
      ? 'Ombi ${statusLabel(status)}'
      : 'Request ${statusLabel(status)}';
  String get availableForRequests =>
      isSwahili ? 'Unapatikana kwa maombi' : 'Available for requests';
  String get unavailableForRequests =>
      isSwahili ? 'Hupatikani kwa maombi' : 'Unavailable for requests';
  String get findTechnician => isSwahili ? 'Tafuta fundi' : 'Find a technician';
  String get clientDashboardSubtitle => isSwahili
      ? 'Eneo lako husasishwa moja kwa moja unapotafuta na kuomba huduma.'
      : 'Live location is synced while you search and request service.';
  String requestsCount(int count) =>
      isSwahili ? 'maombi $count' : '$count requests';
  String get skill => isSwahili ? 'Ujuzi' : 'Skill';
  String get skillHint =>
      isSwahili ? 'Mabomba, AC, umeme' : 'Plumbing, AC, electrical';
  String get requestDetails =>
      isSwahili ? 'Maelezo ya ombi' : 'Request details';
  String get distance => isSwahili ? 'Umbali' : 'Distance';
  String get minimumRating =>
      isSwahili ? 'Ukadiriaji wa chini' : 'Minimum rating';
  String get availableOnly =>
      isSwahili ? 'Mafundi wanaopatikana tu' : 'Available technicians only';
  String get searchTechnicians =>
      isSwahili ? 'Tafuta mafundi' : 'Search technicians';
  String get request => isSwahili ? 'Omba' : 'Request';
  String get matchingTechnicians =>
      isSwahili ? 'Mafundi wanaolingana' : 'Matching technicians';
  String get noActiveSearch =>
      isSwahili ? 'Hakuna matokeo ya utafutaji' : 'No active search results';
  String techniciansFound(int count) =>
      isSwahili ? 'Mafundi $count wamepatikana' : '$count technicians found';
  String get technicianTracking =>
      isSwahili ? 'Ufuatiliaji wa fundi' : 'Technician tracking';
  String get technicianTrackingSubtitle => isSwahili
      ? 'Eneo la fundi husasishwa baada ya ombi kukubaliwa.'
      : 'Technician location updates after a request is accepted.';
  String technicianDistance(double distance) => isSwahili
      ? 'Fundi yuko umbali wa ${distance.toStringAsFixed(2)} km'
      : 'Technician is ${distance.toStringAsFixed(2)} km away';
  String get waitingForTechnicianLocation => isSwahili
      ? 'Inasubiri eneo la fundi...'
      : 'Waiting for technician location...';
  String get clientLocation => isSwahili ? 'Eneo la mteja' : 'Client location';
  String get technicianLocation =>
      isSwahili ? 'Eneo la fundi' : 'Technician location';
  String get locationPending =>
      isSwahili ? 'Eneo halijapatikana' : 'Location pending';
  String etaMinutes(int minutes) =>
      isSwahili ? 'Dakika $minutes' : '$minutes min ETA';
  String get etaPending => isSwahili ? 'ETA inasubiri' : 'ETA pending';
  String get phoneUnavailable => isSwahili ? 'Simu haipo' : 'Phone unavailable';
  String get endRequest => isSwahili ? 'Maliza ombi' : 'End request';
  String get rateTechnician =>
      isSwahili ? 'Mpe fundi alama' : 'Rate the technician';
  String get reportTechnician =>
      isSwahili ? 'Ripoti kuhusu fundi' : 'Report the technician';
  String get reportAbuse => isSwahili ? 'Ripoti unyanyasaji' : 'Report abuse';
  String get reportAbuseTitle => isSwahili
      ? 'Ripoti unyanyasaji au tabia mbaya'
      : 'Report abuse or misconduct';
  String get reportAbuseDetails =>
      isSwahili ? 'Eleza kilichotokea' : 'Describe what happened';
  String get reportSubmitted =>
      isSwahili ? 'Ripoti imetumwa' : 'Report submitted';
  String get submittingReport =>
      isSwahili ? 'Inatuma ripoti...' : 'Submitting report...';
  String get submit => isSwahili ? 'Tuma' : 'Submit';
  String get requestEnded =>
      isSwahili ? 'Ombi limekamilika' : 'Request completed';
  String get technicianDashboard =>
      isSwahili ? 'Dashibodi ya fundi' : 'Technician dashboard';
  String get technicianDashboardTitle =>
      isSwahili ? 'Dashibodi ya Fundi' : 'Technician Dashboard';
  String get technicianDashboardSubtitle => isSwahili
      ? 'Dhibiti upatikanaji, majibu, na historia ya maombi.'
      : 'Manage availability, responses, and request history.';
  String pendingCount(int count) =>
      isSwahili ? '$count yanasubiri' : '$count pending';
  String get availableForNewRequests =>
      isSwahili ? 'Unapatikana kwa maombi mapya' : 'Available for new requests';
  String get liveUpdatesOpen => isSwahili
      ? 'Eneo husasishwa moja kwa moja skrini hii ikiwa wazi'
      : 'Live location updates while this screen is open';
  String get refreshRequests =>
      isSwahili ? 'Sasisha maombi' : 'Refresh requests';
  String get registrationFeeTitle =>
      isSwahili ? 'Ada ya usajili' : 'Registration fee';
  String get refreshPaymentStatus =>
      isSwahili ? 'Sasisha hali ya malipo' : 'Refresh payment status';
  String registrationFeeLine(Object amount, Object currency, String status) =>
      isSwahili
          ? '$currency $amount | ${registrationPaymentStatus(status)}'
          : '$currency $amount | ${registrationPaymentStatus(status)}';
  String registrationPaymentStatus(String status) {
    switch (status) {
      case 'success':
      case 'settled':
        return isSwahili ? 'imelipwa' : 'paid';
      case 'processing':
      case 'pending':
        return isSwahili
            ? 'thibitisha ombi la malipo kwenye simu yako'
            : 'approve the payment prompt on your phone';
      case 'not_configured':
        return isSwahili ? 'haijasanidiwa' : 'not configured';
      case 'request_failed':
        return isSwahili ? 'ombi halikufaulu' : 'request failed';
      case 'unsupported_payment_operator':
        return mpesaNotSupported;
      default:
        return status.isEmpty ? (isSwahili ? 'inasubiri' : 'pending') : status;
    }
  }

  String get newRequests => isSwahili ? 'Maombi mapya' : 'New requests';
  String get noPendingWork =>
      isSwahili ? 'Hakuna kazi inayosubiri' : 'No pending work';
  String waitingForResponse(int count) =>
      isSwahili ? '$count yanasubiri jibu' : '$count waiting for response';
  String get noPendingRequests =>
      isSwahili ? 'Hakuna maombi yanayosubiri' : 'No pending requests';
  String get historyTitle =>
      isSwahili ? 'Historia na maombi ya awali' : 'History & previous requests';
  String get historySubtitle => isSwahili
      ? 'Shughuli za hivi karibuni kwenye akaunti yako'
      : 'Latest activity across your account';
  String get noPreviousRequests =>
      isSwahili ? 'Hakuna maombi ya awali' : 'No previous requests';
  String get generalService =>
      isSwahili ? 'Huduma ya jumla' : 'General service';
  String get accept => isSwahili ? 'Kubali' : 'Accept';
  String get reject => isSwahili ? 'Kataa' : 'Reject';
  String get back => isSwahili ? 'Rudi' : 'Back';
  String get logout => isSwahili ? 'Toka' : 'Logout';
  String get findTechnicianTitle =>
      isSwahili ? 'Tafuta Fundi' : 'Find Technician';
  String get language => isSwahili ? 'Lugha' : 'Language';
  String get english => 'English';
  String get swahili => 'Kiswahili';

  String distanceAway(Object? distance, Object? rating) => isSwahili
      ? '${distance ?? '-'} km mbali | nyota ${rating ?? '-'}'
      : '${distance ?? '-'} km away | ${rating ?? '-'} stars';

  String statusLine(String status, Object? distanceKm) =>
      '${statusLabel(status)} | ${distanceKm ?? '-'} km';

  String statusLabel(String status) {
    switch (status) {
      case 'pending':
        return isSwahili ? 'linasubiri' : 'pending';
      case 'accepted':
        return isSwahili ? 'limekubaliwa' : 'accepted';
      case 'rejected':
        return isSwahili ? 'limekataliwa' : 'rejected';
      case 'completed':
        return isSwahili ? 'limekamilika' : 'completed';
      case 'cancelled':
        return isSwahili ? 'limefutwa' : 'cancelled';
      default:
        return status;
    }
  }
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'sw'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async {
    return AppLocalizations(Locale(locale.languageCode));
  }

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
