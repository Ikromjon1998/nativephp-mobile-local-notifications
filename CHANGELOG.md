# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-03-09

### Added

- **`RepeatInterval` enum** — New `Ikromjon\LocalNotifications\Enums\RepeatInterval` enum with `Minute`, `Hourly`, `Daily`, and `Weekly` cases. Provides IDE autocompletion and type safety. Raw strings (`'daily'`, etc.) are still accepted for backwards compatibility.

### Fixed

- **Android repeating notifications not firing** — Replaced unreliable `AlarmManager.setRepeating()` with `setExactAndAllowWhileIdle()` combined with self-rescheduling in `LocalNotificationReceiver`. Repeating notifications (`repeat: 'daily'`, etc.) now fire reliably on Android 12+ (API 31+) including under Doze mode and battery optimization.
- **BootReceiver now uses exact alarms** — After device reboot, repeating notifications are restored using `setExactAndAllowWhileIdle()` instead of `setRepeating()` for consistent behavior.

## [1.1.0] - 2026-03-08

### Added

- **Test suite & CI pipeline** — Comprehensive Pest test suite with 90%+ code coverage, PHPStan static analysis at level 8, and GitHub Actions CI running on PHP 8.3 and 8.4 ([Epic #1](docs/epics/01-test-suite.md))
- **NotificationReceived event** — Now fires on both iOS and Android when a notification is delivered to the device, including foreground, background, and killed states ([Epic #2](docs/epics/02-fix-missing-event-dispatches.md))
- **NotificationTapped event** — Now fires on both iOS and Android when the user taps a notification, with the full custom `data` payload ([Epic #2](docs/epics/02-fix-missing-event-dispatches.md))
- **Rich notification content** — Support for `subtitle`, `image` (remote URL), and `bigText` (expanded text) in notifications on both platforms ([Epic #3](docs/epics/03-rich-notification-content.md))
- **Action buttons** — Up to 3 interactive action buttons per notification with support for text input actions (`input: true`), destructive styling, and a new `NotificationActionPressed` event ([Epic #4](docs/epics/04-action-buttons.md))
- **NotificationActionPressed event** — New event dispatched when a user taps an action button, providing `notificationId`, `actionId`, and optional `inputText`
- **NotificationTapReceiver** (Android) — Dedicated broadcast receiver for handling notification tap intents
- **NotificationActionReceiver** (Android) — Dedicated broadcast receiver for handling action button presses
- **Queued pending notification events** — Pending notification events are now queued and delivered when the app becomes ready

### Fixed

- **iOS notification body** — Fixed an issue where the notification body was not correctly set on iOS
- **Consistent event payloads** — `NotificationReceived` and `NotificationTapped` now include consistent `id`, `title`, `body`, and `data` fields across both platforms

### Changed

- **composer.json** — Added dev dependencies: `pestphp/pest`, `phpstan/phpstan`, `larastan/larastan`, `orchestra/testbench`
- **nativephp.json** — Added `NotificationTapReceiver`, `NotificationActionReceiver` to Android receivers and `NotificationActionPressed` to events list

## [1.0.0] - 2025-01-01

### Added

- Initial release
- Schedule notifications with delay or specific time
- Repeat intervals: minute, hourly, daily, weekly
- Custom sounds, badges, and data payloads
- Cancel individual or all notifications
- List pending notifications
- Permission management (Android 13+, iOS)
- Survives device reboot (Android)
- NotificationScheduled, PermissionGranted, PermissionDenied events

[1.1.1]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/releases/tag/v1.0.0
