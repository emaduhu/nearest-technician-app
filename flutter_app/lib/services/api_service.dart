import 'dart:convert';
import 'package:http/http.dart' as http;

const SERVER_URL = 'http://10.0.2.2:3000'; // change to your server IP for real devices
// const String SERVER_URL = 'http://192.168.1.10:3000'; // your local IP

class ApiService {
  Future<http.Response> registerDevice(Map<String, dynamic> payload) async {
    final res = await http.post(Uri.parse('$SERVER_URL/api/register'), headers: {'Content-Type': 'application/json'}, body: jsonEncode(payload));
    return res;
  }

  Future<http.Response> requestTechnician(Map<String, dynamic> payload) async {
    final res = await http.post(Uri.parse('$SERVER_URL/api/request'), headers: {'Content-Type': 'application/json'}, body: jsonEncode(payload));
    return res;
  }

  Future<http.Response> getNearest(double lat, double lng) async {
    final res = await http.get(Uri.parse('$SERVER_URL/api/technician/nearest?lat=\$lat&lng=\$lng'));
    return res;
  }

  Future<http.Response> searchNearestBySkill(String skill, double lat, double lon) {
    final uri = Uri.parse('$SERVER_URL/api/technicians/search?skill=${Uri.encodeComponent(skill)}&lat=$lat&lon=$lon');
    return http.get(uri);
  }
}
