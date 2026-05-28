import 'dart:convert';
import 'package:http/http.dart' as http;
import 'api_http_client.dart';

const serverUrl = String.fromEnvironment('SERVER_URL',
    defaultValue: 'https://nt.vigourtech.net');

class ApiException implements Exception {
  final String message;
  final int statusCode;
  final String? code;

  const ApiException(this.message, this.statusCode, {this.code});

  @override
  String toString() => message;
}

class ApiService {
  static Future<http.Client>? _clientFuture;

  static const Map<String, String> _headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  };

  Future<http.Client> get _client => _clientFuture ??= createApiHttpClient();

  Future<Map<String, dynamic>> registerDevice(
      Map<String, dynamic> payload) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.post(Uri.parse('$serverUrl/api/register'),
          headers: _headers, body: jsonEncode(payload)));
    });
  }

  Future<Map<String, dynamic>> login(Map<String, dynamic> payload) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.post(Uri.parse('$serverUrl/api/login'),
          headers: _headers, body: jsonEncode(payload)));
    });
  }

  Future<Map<String, dynamic>> forgotPassword(String email) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.post(
          Uri.parse('$serverUrl/api/forgot-password'),
          headers: _headers,
          body: jsonEncode({'email': email})));
    });
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
    return _withNetworkErrors(() async {
      final client = await _client;
      final body = await _decodeAny(await client.get(uri));
      return body is List ? body : [];
    });
  }

  Future<Map<String, dynamic>> requestTechnician(
      Map<String, dynamic> payload) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.post(Uri.parse('$serverUrl/api/requests'),
          headers: _headers, body: jsonEncode(payload)));
    });
  }

  Future<Map<String, dynamic>> respondToRequest(
      String requestId, Map<String, dynamic> payload) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.patch(
          Uri.parse('$serverUrl/api/requests/$requestId/respond'),
          headers: _headers,
          body: jsonEncode(payload)));
    });
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
    return _withNetworkErrors(() async {
      final client = await _client;
      final body = await _decodeAny(await client.get(
          Uri.parse('$serverUrl/api/requests/history')
              .replace(queryParameters: params)));
      return body is List ? body : [];
    });
  }

  Future<Map<String, dynamic>> updateTechnicianLocation(
      String technicianId, double lat, double lon, bool available) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.patch(
        Uri.parse('$serverUrl/api/technicians/$technicianId/location'),
        headers: _headers,
        body: jsonEncode({'lat': lat, 'lon': lon, 'available': available}),
      ));
    });
  }

  Future<Map<String, dynamic>> updateUserLocation(
      String userId, double lat, double lon) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.patch(
        Uri.parse('$serverUrl/api/users/$userId/location'),
        headers: _headers,
        body: jsonEncode({'lat': lat, 'lon': lon}),
      ));
    });
  }

  Future<Map<String, dynamic>> updateAvailability(
      String technicianId, bool available) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.patch(
        Uri.parse('$serverUrl/api/technicians/$technicianId/availability'),
        headers: _headers,
        body: jsonEncode({'available': available}),
      ));
    });
  }

  Future<Map<String, dynamic>> refreshRegistrationPayment(
      String technicianId) async {
    return _withNetworkErrors(() async {
      final client = await _client;
      return _decode(await client.get(
        Uri.parse(
            '$serverUrl/api/technicians/$technicianId/registration-payment'),
        headers: _headers,
      ));
    });
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

  Future<T> _withNetworkErrors<T>(Future<T> Function() request) async {
    try {
      return await request();
    } on Exception catch (error) {
      _throwNetworkFailure(error);
    }
  }

  Never _throwNetworkFailure(Exception error) {
    if (error is ApiException) {
      throw error;
    }

    final message = error.toString().toLowerCase();
    if (error is http.ClientException ||
        message.contains('socketexception') ||
        message.contains('socketfailed') ||
        message.contains('connection closed') ||
        message.contains('connection reset') ||
        message.contains('clientexception') ||
        message.contains('clientconnection') ||
        message.contains('client connection closed') ||
        message.contains('closed before full header') ||
        message.contains('failed host lookup') ||
        message.contains('host lookup') ||
        message.contains('no address') ||
        message.contains('connection refused')) {
      throw const ApiException(
        'We could not reach the server. Please check your internet connection and try again.',
        0,
        code: 'connection_failure',
      );
    }

    throw error;
  }

  Future<Map<String, dynamic>> _decode(http.Response res) async {
    final body = await _decodeAny(res);
    return body is Map<String, dynamic>
        ? body
        : Map<String, dynamic>.from(body as Map);
  }
}
