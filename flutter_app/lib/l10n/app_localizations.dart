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
  String get phone => isSwahili ? 'Simu' : 'Phone';
  String get phoneRequired =>
      isSwahili ? 'Namba ya simu inahitajika' : 'Phone number is required';
  String get mpesaNotSupported => isSwahili
      ? 'M-Pesa bado haipokelewi. Tumia namba ya Yas, Airtel au Halopesa.'
      : 'M-Pesa is not supported yet. Use a Yas, Airtel, or Halopesa number.';
  String get registrationFeeNotice => isSwahili
      ? 'Ada ya usajili ni TZS 5000. Malipo kwa sasa yanapokelewa kupitia Yas, Airtel na Halopesa. Ukipokea ombi la malipo lenye jina CLICKPESA, ni sahihi; endelea kulipa. Tunaongeza M-Pesa hivi karibuni, kwa sasa usitumie namba ya M-Pesa.'
      : 'Registration fee is TZS 5000. Payments currently work with Yas, Airtel, and Halopesa. If the payment prompt shows CLICKPESA, that is correct; proceed with payment. We are adding M-Pesa soon, so do not use an M-Pesa number for the push.';
  String get registrationFeeOperators => isSwahili
      ? 'Tumia Yas, Airtel au Halopesa. Ombi likionyesha CLICKPESA, endelea. M-Pesa itaongezwa hivi karibuni.'
      : 'Use Yas, Airtel, or Halopesa. If the prompt shows CLICKPESA, proceed. M-Pesa is coming soon.';
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
  String get liveLocationUpdateFailed => isSwahili
      ? 'Tatizo la muunganisho'
      : 'Connection failure';
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
