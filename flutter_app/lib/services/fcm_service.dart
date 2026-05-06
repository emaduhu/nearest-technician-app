import 'package:firebase_messaging/firebase_messaging.dart';

class FcmService {
  Future<String?> init(Function(Map<String, dynamic>) onMessage) async {
    try {
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.authorized) {
        FirebaseMessaging.onMessage.listen((RemoteMessage msg) {
          onMessage(msg.data);
        });
        FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage msg) {
          onMessage(msg.data);
        });
        FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);
        return await messaging.getToken();
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  static Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
    // background handler
  }
}
