import 'package:flutter/material.dart';
import '../services/fcm_service.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class RegisterPage extends StatefulWidget {
  final Function onRegistered;

  const RegisterPage({required this.onRegistered, Key? key}) : super(key: key);

  @override
  _RegisterPageState createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final FcmService _fcm = FcmService();
  final ApiService _api = ApiService();

  String _status = '';
  String? _token;

  @override
  void initState() {
    super.initState();
    _initializeFcm();
  }

  Future<void> _initializeFcm() async {
    final token = await _fcm.init((data) {
      debugPrint('FCM data: $data');
    });
    setState(() => _token = token);
  }

  Future<void> _register(String role) async {
    setState(() => _status = 'Getting location...');
    final pos = await LocationService.getCurrentPosition();

    if (pos == null) {
      setState(() => _status = 'Location denied');
      return;
    }

    if (_token == null) {
      setState(() => _status = 'Waiting for FCM token...');
      return;
    }

    final namePrefix = role == 'technician' ? 'Tech' : 'Client';
    final payload = {
      'role': role,
      'token': _token,
      'lat': pos.latitude,
      'lon': pos.longitude,
      'name': '$namePrefix-${DateTime.now().millisecondsSinceEpoch}',
    };

    setState(() => _status = 'Registering...');
    final res = await _api.registerDevice(payload);

    if (res.statusCode == 200) {
      setState(() => _status = 'Registered as $role');
      widget.onRegistered();
    } else {
      setState(() => _status = 'Error: ${res.statusCode} ${res.body}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Register')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            ElevatedButton(
              onPressed: () => _register('technician'),
              child: const Text('Register as Technician'),
            ),
            const SizedBox(height: 10),
            ElevatedButton(
              onPressed: () => _register('client'),
              child: const Text('Register as Client'),
            ),
            const SizedBox(height: 20),
            Text('Status: $_status'),
            const SizedBox(height: 10),
            Text(
              'FCM Token: ${_token ?? '...'}',
              style: const TextStyle(fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
