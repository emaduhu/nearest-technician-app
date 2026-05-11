import 'package:http/http.dart' as http;

Future<http.Client> createPlatformHttpClient() async => http.Client();
