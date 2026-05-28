import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'l10n/app_localizations.dart';
import 'services/update_check_service.dart';
import 'services/fcm_service.dart';
import 'widgets/register_page.dart';
import 'widgets/home_page.dart';
import 'package:upgrader/upgrader.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  FcmService.registerBackgroundHandler();
  runApp(const MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  Map<String, dynamic>? _session;
  Locale _locale = const Locale('en');

  void _setLocale(Locale locale) {
    setState(() => _locale = locale);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      debugShowCheckedModeBanner: false,
      locale: _locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ],
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF0F766E),
          primary: const Color(0xFF0F766E),
          secondary: const Color(0xFF2563EB),
          surface: Colors.white,
        ),
        scaffoldBackgroundColor: const Color(0xFFF5F7F8),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 0,
          backgroundColor: Color(0xFFF5F7F8),
          foregroundColor: Color(0xFF17202A),
        ),
        cardTheme: CardThemeData(
          elevation: 0,
          margin: const EdgeInsets.only(bottom: 12),
          color: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
            side: const BorderSide(color: Color(0xFFDCE4E8)),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: const BorderSide(color: Color(0xFFDCE4E8)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: const BorderSide(color: Color(0xFFDCE4E8)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: const BorderSide(color: Color(0xFF0F766E), width: 1.4),
          ),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: const Color(0xFFEEF5F3),
          side: BorderSide.none,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            minimumSize: const Size(0, 46),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          ),
        ),
      ),
      builder: (context, child) {
        return ForceUpgradeAlert(
          upgrader: UpdateCheckService.upgraderFor(_locale),
          barrierDismissible: false,
          dialogStyle: UpgradeDialogStyle.material,
          shouldPopScope: () => false,
          showIgnore: false,
          showLater: false,
          showReleaseNotes: true,
          child: child ?? const SizedBox.shrink(),
        );
      },
      home: _session == null
          ? RegisterPage(
              locale: _locale,
              onLocaleChanged: _setLocale,
              onRegistered: (session) => setState(() => _session = session))
          : HomePage(
              locale: _locale,
              onLocaleChanged: _setLocale,
              session: _session!,
              onLogout: () => setState(() => _session = null),
            ),
    );
  }
}

class ForceUpgradeAlert extends UpgradeAlert {
  ForceUpgradeAlert({
    required super.upgrader,
    required super.barrierDismissible,
    required super.dialogStyle,
    required super.shouldPopScope,
    required super.showIgnore,
    required super.showLater,
    required super.showReleaseNotes,
    required super.child,
    super.key,
  });

  @override
  UpgradeAlertState createState() => _ForceUpgradeAlertState();
}

class _ForceUpgradeAlertState extends UpgradeAlertState {
  @override
  void onUserUpdated(BuildContext context, bool shouldPop) {
    widget.upgrader.sendUserToAppStore();
  }
}
