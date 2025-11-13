import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'firebase_options.dart';
import 'widgets/register_page.dart';
import 'widgets/home_page.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Initialize with firebase options. Replace placeholders in lib/firebase_options.dart with real values from `flutterfire configure`.
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  runApp(MyApp());
}

class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  bool _registered = false;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Nearest Technician',
      home: _registered ? HomePage() : RegisterPage(onRegistered: () { setState(() => _registered = true); }),
    );
  }
}
