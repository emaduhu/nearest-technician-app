import 'package:flutter/material.dart';
import 'package:upgrader/upgrader.dart';

class UpdateCheckService {
  static final Map<String, Upgrader> _cache = {};
  static const String minimumSupportedVersion = '1.0.1';

  static Upgrader upgraderFor(Locale locale) {
    final languageCode = locale.languageCode;
    return _cache.putIfAbsent(
        languageCode,
        () => Upgrader(
              countryCode: 'TZ',
              debugDisplayAlways: false,
              debugDisplayOnce: false,
              debugLogging: false,
              languageCode: languageCode,
              minAppVersion: minimumSupportedVersion,
              showOnlyMandatoryUpdates: false,
              messages: languageCode == 'sw'
                  ? SwahiliUpgraderMessages()
                  : ForcedEnglishUpgraderMessages(),
              durationUntilAlertAgain: Duration.zero,
            ));
  }
}

class ForcedEnglishUpgraderMessages extends UpgraderMessages {
  ForcedEnglishUpgraderMessages() : super(code: 'en');

  @override
  String? message(UpgraderMessage messageKey) {
    switch (messageKey) {
      case UpgraderMessage.body:
        return 'A new version of {{appName}} is available. Version {{currentAppStoreVersion}} is ready; you are using {{currentInstalledVersion}}.';
      case UpgraderMessage.buttonTitleIgnore:
        return 'Ignore';
      case UpgraderMessage.buttonTitleLater:
        return 'Later';
      case UpgraderMessage.buttonTitleUpdate:
        return 'Update now';
      case UpgraderMessage.prompt:
        return 'You must update the app to continue using the service.';
      case UpgraderMessage.releaseNotes:
        return 'Release notes';
      case UpgraderMessage.title:
        return 'Update required';
    }
  }
}

class SwahiliUpgraderMessages extends UpgraderMessages {
  SwahiliUpgraderMessages() : super(code: 'sw');

  @override
  String? message(UpgraderMessage messageKey) {
    switch (messageKey) {
      case UpgraderMessage.body:
        return 'Toleo jipya la {{appName}} linapatikana. Toleo {{currentAppStoreVersion}} linapatikana sasa; unatumia {{currentInstalledVersion}}.';
      case UpgraderMessage.buttonTitleIgnore:
        return 'Puuza';
      case UpgraderMessage.buttonTitleLater:
        return 'Baadaye';
      case UpgraderMessage.buttonTitleUpdate:
        return 'Sasisha sasa';
      case UpgraderMessage.prompt:
        return 'Ni lazima usasishe programu ili kuendelea kutumia huduma.';
      case UpgraderMessage.releaseNotes:
        return 'Maelezo ya toleo';
      case UpgraderMessage.title:
        return 'Sasisho linahitajika';
    }
  }
}
