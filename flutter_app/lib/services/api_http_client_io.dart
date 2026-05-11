import 'dart:io';

import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';

Future<http.Client> createPlatformHttpClient() async {
  final context = SecurityContext(withTrustedRoots: true);
  final data = await rootBundle.load('assets/certificates/isrg_root_x1.pem');
  final bytes = Uint8List.view(data.buffer);
  context.setTrustedCertificatesBytes(bytes);

  return IOClient(HttpClient(context: context));
}
