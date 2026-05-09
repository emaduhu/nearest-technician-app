import 'dart:convert';
import 'package:http/http.dart' as http;

const serverUrl = String.fromEnvironment('SERVER_URL',
    defaultValue: 'https://nt-api.vigourtech.net');

class ApiException implements Exception {
  final String message;
  final int statusCode;
  final String? code;

  const ApiException(this.message, this.statusCode, {this.code});

  @override
  String toString() => message;
}

class ApiService {
  static const Map<String, String> _headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  };

  Future<Map<String, dynamic>> registerDevice(
      Map<String, dynamic> payload) async {
    return _decode(await http.post(Uri.parse('$serverUrl/api/register'),
        headers: _headers, body: jsonEncode(payload)));
  }

  Future<Map<String, dynamic>> login(Map<String, dynamic> payload) async {
    return _decode(await http.post(Uri.parse('$serverUrl/api/login'),
        headers: _headers, body: jsonEncode(payload)));
  }

  Future<List<dynamic>> searchTechnicians({
    required String skill,
    required double lat,
    required double lon,
    required double maxDistanceKm,
    required bool available,
    required double minRating,
  }) async {
    final uri = Uri.parse('$serverUrl/api/technicians/search')
        .replace(queryParameters: {
      'skill': skill,
      'lat': '$lat',
      'lon': '$lon',
      'maxDistanceKm': '$maxDistanceKm',
      'available': '$available',
      'minRating': '$minRating',
    });
    final body = await _decodeAny(await http.get(uri));
    return body is List ? body : [];
  }

  Future<Map<String, dynamic>> requestTechnician(
      Map<String, dynamic> payload) async {
    return _decode(await http.post(Uri.parse('$serverUrl/api/requests'),
        headers: _headers, body: jsonEncode(payload)));
  }

  Future<Map<String, dynamic>> respondToRequest(
      String requestId, Map<String, dynamic> payload) async {
    return _decode(await http.patch(
        Uri.parse('$serverUrl/api/requests/$requestId/respond'),
        headers: _headers,
        body: jsonEncode(payload)));
  }

  Future<List<dynamic>> getHistory(
      {String? clientId, String? technicianId, String? status}) async {
    final params = <String, String>{};
    if (clientId != null && clientId.isNotEmpty) {
      params['clientId'] = clientId;
    }
    if (technicianId != null && technicianId.isNotEmpty) {
      params['technicianId'] = technicianId;
    }
    if (status != null && status.isNotEmpty) params['status'] = status;
    final body = await _decodeAny(await http.get(
        Uri.parse('$serverUrl/api/requests/history')
            .replace(queryParameters: params)));
    return body is List ? body : [];
  }

  Future<Map<String, dynamic>> updateTechnicianLocation(
      String technicianId, double lat, double lon, bool available) async {
    return _decode(await http.patch(
      Uri.parse('$serverUrl/api/technicians/$technicianId/location'),
      headers: _headers,
      body: jsonEncode({'lat': lat, 'lon': lon, 'available': available}),
    ));
  }

  Future<Map<String, dynamic>> updateUserLocation(
      String userId, double lat, double lon) async {
    return _decode(await http.patch(
      Uri.parse('$serverUrl/api/users/$userId/location'),
      headers: _headers,
      body: jsonEncode({'lat': lat, 'lon': lon}),
    ));
  }

  Future<Map<String, dynamic>> updateAvailability(
      String technicianId, bool available) async {
    return _decode(await http.patch(
      Uri.parse('$serverUrl/api/technicians/$technicianId/availability'),
      headers: _headers,
      body: jsonEncode({'available': available}),
    ));
  }

  Future<dynamic> _decodeAny(http.Response res) async {
    final dynamic body = _tryDecode(res.body);
    if (res.statusCode < 200 || res.statusCode >= 300) {
      final message = body is Map && body['error'] != null
          ? body['error'].toString()
          : body is Map && body['message'] != null
              ? body['message'].toString()
              : 'HTTP ${res.statusCode}';
      throw ApiException(
        message,
        res.statusCode,
        code: body is Map ? body['code']?.toString() : null,
      );
    }
    if (body == null && res.body.isNotEmpty) {
      throw Exception('Server returned an invalid response');
    }
    return body;
  }

  dynamic _tryDecode(String body) {
    if (body.isEmpty) return null;
    try {
      return jsonDecode(body);
    } on FormatException {
      return null;
    }
  }

  Future<Map<String, dynamic>> _decode(http.Response res) async {
    final body = await _decodeAny(res);
    return body is Map<String, dynamic>
        ? body
        : Map<String, dynamic>.from(body as Map);
  }
}
