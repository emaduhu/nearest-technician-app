// lib/widgets/home_page.dart
import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import '../services/location_service.dart';
import '../services/api_service.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final ApiService _api = ApiService();
  final TextEditingController _searchCtrl = TextEditingController();
  Timer? _debounce;

  String _status = '';
  bool _loading = false;
  List<dynamic> _results = [];

  // debounce wrapper
  void _onSearchChanged(String value) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      _searchSkill(value.trim());
    });
  }

  Future<void> _searchSkill(String skill) async {
    if (skill.isEmpty) {
      setState(() {
        _results = [];
        _status = '';
      });
      return;
    }

    setState(() {
      _loading = true;
      _status = 'Getting location...';
    });

    final pos = await LocationService.getCurrentPosition();
    if (pos == null) {
      setState(() {
        _loading = false;
        _status = 'Location denied';
      });
      return;
    }

    setState(() => _status = 'Searching nearest technicians for "$skill"...');

    try {
      final res = await _api.searchNearestBySkill(skill, pos.latitude, pos.longitude);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        // server returns { ok: true, nearest: [...] } OR an array; handle both
        final data = body is Map && body['nearest'] != null ? body['nearest'] : (body is List ? body : []);
        setState(() {
          _results = List.from(data);
          _status = _results.isEmpty ? 'No technicians found nearby' : 'Found ${_results.length}';
        });
      } else {
        setState(() {
          _status = 'Error: ${res.statusCode}';
          _results = [];
        });
      }
    } catch (e) {
      setState(() {
        _status = 'Error: ${e.toString()}';
        _results = [];
      });
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _requestTechnician(dynamic tech) async {
    setState(() => _status = 'Requesting ${tech['name']}...');
    final pos = await LocationService.getCurrentPosition();
    if (pos == null) {
      setState(() => _status = 'Location denied');
      return;
    }

    final payload = {
      'clientName': 'Client-${DateTime.now().millisecondsSinceEpoch}',
      'lat': pos.latitude,
      'lon': pos.longitude,
      'technicianId': tech['id'] ?? tech['_id'] ?? tech[' _id'] ?? '',
    };

    try {
      final res = await _api.requestTechnician(payload);
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['pushed'] == true) {
          setState(() => _status = 'Request sent successfully to ${tech['name']}');
        } else {
          setState(() => _status = 'Request sent, but push not delivered: ${data['message'] ?? 'Technician has no FCM token'}');
        }
      } else {
        setState(() => _status = 'Error: ${res.statusCode} ${res.body}');
      }
    } catch (e) {
      setState(() => _status = 'Error sending request: $e');
    }
  }

  Widget _buildCard(Map<String, dynamic> tech) {
    final image = tech['image'] ?? '';
    final skills = tech['skills'] ?? [];
    final distance = tech['distance'] ?? tech['distance_km']?.toString();
    final available = tech['available'] == true;

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8),
      elevation: 3,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            CircleAvatar(
              radius: 34,
              backgroundImage: image.isNotEmpty ? NetworkImage(image) : null,
              child: image.isEmpty ? const Icon(Icons.person, size: 34) : null,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(tech['name'] ?? 'Unknown', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                const SizedBox(height: 4),
                Text('📞 ${tech['phone'] ?? 'N/A'}', style: const TextStyle(fontSize: 13)),
                const SizedBox(height: 4),
                if (skills is List && skills.isNotEmpty)
                  Wrap(spacing: 6, children: skills.map<Widget>((s) => Chip(label: Text(s.toString(), style: const TextStyle(fontSize: 12)))).toList()),
                const SizedBox(height: 6),
                Row(children: [
                  if (distance != null) Text('${distance} km', style: const TextStyle(fontSize: 13)),
                  const SizedBox(width: 8),
                  Text(available ? '✅ Available' : '❌ Unavailable', style: const TextStyle(fontSize: 13)),
                ]),
              ]),
            ),
            const SizedBox(width: 8),
            ElevatedButton(
              onPressed: available ? () => _requestTechnician(tech) : null,
              child: const Text('Request'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Find Technician'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Navigator.of(context).maybePop()),
      ),
      body: LayoutBuilder(builder: (context, constraints) {
        final maxWidth = constraints.maxWidth;
        final padding = maxWidth > 600 ? 32.0 : 16.0;
        return SingleChildScrollView(
          padding: EdgeInsets.all(padding),
          child: Column(
            children: [
              TextField(
                controller: _searchCtrl,
                decoration: InputDecoration(
                  hintText: 'Search by skill (e.g., Plumbing)',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _searchCtrl.text.isNotEmpty
                      ? IconButton(icon: const Icon(Icons.clear), onPressed: () {
                    _searchCtrl.clear();
                    _onSearchChanged('');
                  })
                      : null,
                ),
                onChanged: _onSearchChanged,
              ),
              const SizedBox(height: 12),
              if (_loading) const LinearProgressIndicator(),
              const SizedBox(height: 8),
              Align(alignment: Alignment.centerLeft, child: Text('Status: $_status', style: TextStyle(color: Colors.grey[700]))),
              const SizedBox(height: 8),
              _results.isEmpty
                  ? Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: Text(_status.isEmpty ? 'Type a skill to search' : _status, textAlign: TextAlign.center),
              )
                  : ListView.builder(
                physics: const NeverScrollableScrollPhysics(),
                shrinkWrap: true,
                itemCount: _results.length,
                itemBuilder: (c, i) => _buildCard(Map<String, dynamic>.from(_results[i])),
              ),
            ],
          ),
        );
      }),
    );
  }
}
