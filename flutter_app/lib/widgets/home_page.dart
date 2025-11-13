import 'dart:convert';
import 'package:flutter/material.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class HomePage extends StatefulWidget {
  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final ApiService _api = ApiService();
  String _status = '';
  Map<String, dynamic>? _nearestData;

  Future<void> _requestTechnician() async {
    setState(() => _status = 'Getting location...');
    final pos = await LocationService.getCurrentPosition();
    if (pos == null) {
      setState(() => _status = 'Location denied');
      return;
    }

    final payload = {
      'clientName': 'Client-${DateTime.now().millisecondsSinceEpoch}',
      'lat': pos.latitude,
      'lon': pos.longitude,
    };

    setState(() => _status = 'Requesting...');
    final res = await _api.requestTechnician(payload);

    if (res.statusCode == 200) {
      setState(() => _status = 'Request sent');
    } else {
      setState(() => _status = 'Error: ${res.statusCode} ${res.body}');
    }
  }

  Future<void> _getNearestTechnician() async {
    setState(() => _status = 'Getting location...');

    final pos = await LocationService.getCurrentPosition();
    if (pos == null) {
      setState(() => _status = 'Location denied');
      return;
    }

    setState(() => _status = 'Fetching nearest technician...');
    final res = await _api.getNearest(pos.latitude, pos.longitude);

    if (res.statusCode == 200) {
      final data = jsonDecode(res.body);

      if (data != null && data.isNotEmpty) {
        setState(() {
          _nearestData = data;
          _status = 'Nearest technician loaded: ${_nearestData!['name'] ?? _nearestData!['email']}';
        });
        debugPrint('✅ Loaded Technician: $_nearestData');
      } else {
        setState(() => _status = 'No technicians found nearby');
      }
    } else {
      setState(() => _status = 'Error: ${res.statusCode}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Home')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            ElevatedButton(
              onPressed: _requestTechnician,
              child: const Text('Request Nearest Technician'),
            ),
            const SizedBox(height: 10),
            ElevatedButton(
              onPressed: _getNearestTechnician,
              child: const Text('Get Nearest Technician'),
            ),
            const SizedBox(height: 20),
            if (_nearestData != null) ...[
              Text(
                'Nearest: ${_nearestData!['name'] ?? _nearestData!['email'] ?? 'Unknown'}',
              ),
              Text(
                'Location: ${_nearestData!['latitude'] ?? _nearestData!['lat']}, '
                    '${_nearestData!['longitude'] ?? _nearestData!['lon']}',
              ),
            ],
            const SizedBox(height: 20),
            Text('Status: $_status'),
          ],
        ),
      ),
    );
  }
}
