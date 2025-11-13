import 'package:firebase_messaging/firebase_messaging.dart';

class FcmService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  Future<String?> init(Function(Map<String, dynamic>) onMessage) async {
    final settings = await _messaging.requestPermission();
    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      FirebaseMessaging.onMessage.listen((RemoteMessage msg) {
        onMessage(msg.data);
      });
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage msg) {
        onMessage(msg.data);
      });
      FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);
      return await _messaging.getToken();
    }
    return null;
  }

  static Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
    // background handler
  }
}
