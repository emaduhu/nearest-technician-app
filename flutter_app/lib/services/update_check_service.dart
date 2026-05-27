import 'package:flutter/material.dart';
import 'package:upgrader/upgrader.dart';

class UpdateCheckService {
  static final Map<String, Upgrader> _cache = {};

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
              messages: languageCode == 'sw'
                  ? SwahiliUpgraderMessages()
                  : UpgraderMessages(code: 'en'),
              durationUntilAlertAgain: Duration.zero,
            ));
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
