// Generated from the checked-in Firebase Android and iOS client files.
// Re-run `flutterfire configure` after changing Firebase apps in the console.

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError(
        'Firebase options are not configured for web.',
      );
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        throw UnsupportedError(
          'Firebase options are not configured for this platform.',
        );
    }
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyAG_zxZVjMJ90xbT57lCcR1nQBJC8632QY',
    appId: '1:1006137562010:android:3c8965ff4cc4960bc32023',
    messagingSenderId: '1006137562010',
    projectId: 'nearest-technician',
    storageBucket: 'nearest-technician.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyBymfmvm1HWVwcjBEBkSpLh9VHPZGXNYwI',
    appId: '1:1006137562010:ios:3814c60bd8c24274c32023',
    messagingSenderId: '1006137562010',
    projectId: 'nearest-technician',
    storageBucket: 'nearest-technician.firebasestorage.app',
    iosBundleId: 'net.vigourtech.nt',
  );
}
