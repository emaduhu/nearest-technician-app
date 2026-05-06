import 'package:flutter/material.dart';
import 'package:upgrader/upgrader.dart';

class UpdateCheckService {
  static final Map<String, Upgrader> _cache = {};

  static Upgrader upgraderFor(Locale locale) {
    final languageCode = locale.languageCode;
    return _cache.putIfAbsent(
        languageCode,
        () => Upgrader(
              languageCode: languageCode,
              messages: languageCode == 'sw'
                  ? SwahiliUpgraderMessages()
                  : UpgraderMessages(code: 'en'),
              durationUntilAlertAgain: const Duration(days: 1),
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
        return 'Sasisha programu ili kupata maboresho na marekebisho ya hivi karibuni.';
      case UpgraderMessage.releaseNotes:
        return 'Maelezo ya toleo';
      case UpgraderMessage.title:
        return 'Sasisho linapatikana';
    }
  }
}
