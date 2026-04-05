# Epic 8: Custom Notification Sounds

**Priority:** Medium
**Status:** Done (v1.9.0)

## Prerequisites

- v1.4.0 added `default_sound` config option controlling the default `sound: true/false` behavior.

## Description

Allow specifying custom sound files instead of just a boolean on/off toggle. Developers need distinct sounds for different notification types (e.g., alerts vs. reminders). The default sound toggle is already configurable via `config/local-notifications.php` (`default_sound` key).

## Scope

- Add optional `soundName` parameter to schedule options accepting a filename (e.g., `"alert.wav"`)
- Define a convention for where custom sound files should be placed in the project (e.g., `resources/sounds/`)
- **iOS:** Set `UNNotificationSound(named:)` with the custom sound file. Sound files must be in the app bundle (CAF, AIFF, or WAV format, under 30 seconds). Document the process for adding sounds to the iOS bundle via NativePHP plugin resources
- **Android:** Place sound files in `res/raw/` directory. Set the sound URI on the notification channel or directly on the notification builder via `setSound()`
- Keep `sound: true` as default system sound, `sound: false` as silent, and `soundName: "file"` for custom sounds
- Add validation that the sound file exists and is in a supported format
- Document supported formats: WAV, AIFF/CAF (iOS), OGG/WAV/MP3 (Android)

## Acceptance Criteria

- [x] Custom sound files play when the notification is delivered
- [x] Default sound still works with `sound: true`
- [x] Silent notifications work with `sound: false`
- [x] Helpful error when sound file is not found or in unsupported format
- [ ] Documentation covers how to add sound files for each platform
