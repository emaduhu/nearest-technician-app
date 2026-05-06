# Deployment

## Brand and Store Assets

Master logo source:
- `assets/brand/nearest_technician_logo.svg`

Generated brand exports:
- `assets/brand/logo-mark-1024.png`
- `assets/brand/logo-mark-transparent-1024.png`

Google Play assets:
- `store_assets/play_store/app-icon-512.png`
- `store_assets/play_store/feature-graphic-1024x500.png`
- `store_assets/play_store/screenshots/phone-01-client-search.png`
- `store_assets/play_store/screenshots/phone-02-technician-jobs.png`
- `store_assets/play_store/screenshots/phone-03-operations.png`

App Store asset:
- `store_assets/app_store/app-icon-1024.png`

Regenerate all app and store images:

```bash
python3 tools/generate_deployment_assets.py
```

## Android

Generated launcher icons are under:
- `flutter_app/android/app/src/main/res/mipmap-*`
- `flutter_app/android/app/src/main/res/mipmap-anydpi-v26`

Before Play Store upload, create `flutter_app/android/key.properties` from:

```bash
cp flutter_app/android/key.properties.example flutter_app/android/key.properties
```

Then update the passwords, alias, and keystore path.

Build an Android App Bundle:

```bash
cd flutter_app
flutter build appbundle --release --dart-define=SERVER_URL=https://your-api-domain.com
```

Output:
- `flutter_app/build/app/outputs/bundle/release/app-release.aab`

Update checks are enabled with the `upgrader` package. Android checks the public Google Play Store listing for `com.evaristusmaduhu.technicians`, then opens Google Play when the user taps update. This only works after the app has a public Play Store listing, or with an appcast if you add one later for internal testing.

## iOS

Generated AppIcon assets are under:
- `flutter_app/ios/Runner/Assets.xcassets/AppIcon.appiconset`

The iOS display name and location permission strings are set in:
- `flutter_app/ios/Runner/Info.plist`

Archive from macOS with Xcode or:

```bash
cd flutter_app
flutter build ipa --release --dart-define=SERVER_URL=https://your-api-domain.com
```

Configure the final bundle identifier, team, signing certificate, and provisioning profile in Xcode.

Update checks are enabled with the `upgrader` package. iOS checks the public App Store listing by bundle ID and opens the App Store when the user taps update. If the app is not in the US App Store, configure `countryCode` in `UpdateCheckService.upgraderFor`.

## Web

Generated web icons are under:
- `flutter_app/web/favicon.png`
- `flutter_app/web/icons/`

Build the production web app:

```bash
cd flutter_app
flutter build web --release --dart-define=SERVER_URL=https://your-api-domain.com
```

Output:
- `flutter_app/build/web`
