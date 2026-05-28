import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

class FcmService {
  static void registerBackgroundHandler() {
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
  }

  Future<String?> init(
    Function(Map<String, dynamic>) onMessage, {
    Function(String token)? onTokenRefresh,
  }) async {
    try {
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.requestPermission();
      final allowed =
          settings.authorizationStatus == AuthorizationStatus.authorized ||
              settings.authorizationStatus == AuthorizationStatus.provisional;

      if (allowed) {
        await messaging.setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        );

        FirebaseMessaging.onMessage.listen((RemoteMessage msg) {
          onMessage(_payloadFor(msg));
        });
        FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage msg) {
          onMessage(_payloadFor(msg));
        });
        if (onTokenRefresh != null) {
          FirebaseMessaging.instance.onTokenRefresh.listen(onTokenRefresh);
        }

        return await messaging.getToken();
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  Map<String, dynamic> _payloadFor(RemoteMessage message) {
    return {
      ...message.data,
      if (message.notification?.title != null)
        'title': message.notification!.title!,
      if (message.notification?.body != null)
        'body': message.notification!.body!,
    };
  }
}
