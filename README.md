Nearest Technician App - Final
=============================

This package includes:
- flutter_app/: Flutter client (uses Firebase for push). firebase_options.dart includes projectId 'nearest-technician-app' but still contains placeholders.
- server/: Node.js backend (uses local MongoDB at mongodb://localhost:27017/technician_app)

Important: some sensitive credentials cannot be included for security reasons (API keys, service account JSON).
You must perform a couple of final steps before running.

1) Server setup (local MongoDB)
------------------------------
cd server
npm install
# seed the DB (creates dummy technician)
npm run seed
# start server
npm start

Server will connect to MongoDB at mongodb://localhost:27017/technician_app by default.

2) Firebase (Flutter client)
----------------------------
The Flutter app's firebase_options.dart contains the projectId you provided but still requires real API keys.
Install FlutterFire CLI and generate the config:

# install flutterfire CLI
dart pub global activate flutterfire_cli
export PATH="$PATH:$HOME/.pub-cache/bin"

# inside flutter_app
cd flutter_app
flutterfire configure --project nearest-technician-app

This will overwrite lib/firebase_options.dart with real values.

3) Firebase Admin for server push notifications
----------------------------------------------
If you want the server to send FCM pushes, download your Firebase service account JSON:
- Firebase Console -> Project Settings -> Service Accounts -> Generate new private key
Place the file as server/serviceAccountKey.json

4) Run the app
--------------
# Flutter
cd flutter_app
flutter pub get
flutter run

# Test APIs (examples)
curl http://localhost:3000/api/technician/nearest?lat=-6.8&lng=39.2
curl -X POST http://localhost:3000/api/request -H "Content-Type: application/json" -d '{"clientName":"Client1","lat":-6.8,"lon":39.2}'

Notes:
- The Flutter app registers device tokens with the server when a user registers as technician.
- The seeded technician has empty deviceToken initially; register a technician from the app to populate it.
- I cannot include API keys or service account JSON for your Firebase project for security reasons. You must run `flutterfire configure` and add the service account JSON yourself.

