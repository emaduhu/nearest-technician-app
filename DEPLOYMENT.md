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
flutter build appbundle --release --dart-define=SERVER_URL=https://nt.vigourtech.net
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
flutter build ipa --release --dart-define=SERVER_URL=https://nt.vigourtech.net
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
flutter build web --release --dart-define=SERVER_URL=https://nt.vigourtech.net
```

Output:
- `flutter_app/build/web`

## Laravel API

The Laravel API is in `laravel_api/` and keeps the same `/api/...` contract used by the existing Node API. The Node API in `server/` is still available; the app and portal now default to Laravel (`8000`) for new runs.

Production MySQL environment:

```bash
cd laravel_api
cp .env.example .env
php artisan key:generate
```

Set these values in `laravel_api/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nearest_technician
DB_USERNAME=your-db-user
DB_PASSWORD=your-password
APP_URL=https://nt.vigourtech.net
APP_FRONTEND_URL=https://nt.vigourtech.net
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.nt.vigourtech.net
MAIL_PORT=465
MAIL_USERNAME=support@nt.vigourtech.net
MAIL_PASSWORD=your-mailbox-password
MAIL_FROM_ADDRESS=support@nt.vigourtech.net
MAIL_FROM_NAME="${APP_NAME}"
FCM_SERVER_KEY=
SMS_PROVIDER=firebase
BEEM_ACCESS_KEY=
BEEM_SECRET_KEY=
BEEM_OTP_APP_ID=
BEEM_OTP_REQUEST_URL=https://apiotp.beem.africa/v1/request
BEEM_OTP_VERIFY_URL=https://apiotp.beem.africa/v1/verify
CLICKPESA_CLIENT_ID=your-clickpesa-client-id
CLICKPESA_API_KEY=your-clickpesa-api-key
CLICKPESA_CURRENCY=TZS
CLICKPESA_TECHNICIAN_REGISTRATION_FEE=5000
```

Technician registration triggers a ClickPesa USSD push for the configured registration fee. The technician phone number must be a mobile-money number; local numbers such as `0712345678` are normalized to `255712345678` before sending to ClickPesa.

SMS/OTP verification is selected from the admin portal. Firebase uses the mobile app client configuration. Beem Africa uses the backend `BEEM_*` OTP credentials and returns a backend-issued phone verification token to the app after the PIN is verified.

Install and start:

```bash
cd laravel_api
composer install --no-dev --optimize-autoloader
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Production Flutter builds should point at the deployed Laravel domain:

```bash
cd flutter_app
flutter build appbundle --release --dart-define=SERVER_URL=https://nt.vigourtech.net
flutter build web --release --dart-define=SERVER_URL=https://nt.vigourtech.net
```

## Laravel Portal

The Laravel app also serves the operations portal and password reset pages:

- API, portal, and password reset domain: `https://nt.vigourtech.net`

Point both cPanel subdomains at the same Laravel deployment pattern, with public files in the subdomain document root and the application in the protected `laravel/` folder. The password reset email links use `APP_FRONTEND_URL`, so set it to the portal domain.
