# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-03-18

### Added

- **`<x-local-notifications::init />` Blade component** — Drop-in component that auto-flushes cold-start notification tap events on page load. Add it once to your app layout and `NotificationTapped` events from cold starts are delivered automatically — no manual `checkPermission()` call in `mount()` needed. ([#14](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/14))
- **View namespace registration** — Service provider now registers a `local-notifications` view namespace for the Blade component.
- **Publishable config file** — `config/local-notifications.php` with all customizable values: channel ID/name/description, max actions, min repeat interval, default sound, tap detection delay, and navigation replay duration. Publish with `php artisan vendor:publish --tag=local-notifications-config`.
- **ServiceProvider `boot()` method** — Registers config merging and publishing for the `local-notifications` config key.
- **Config-driven validation** — `NotificationValidator` now reads `max_actions` and `min_repeat_interval_seconds` from config instead of hardcoded values. Validates action button count at the PHP layer, including when using the `NotificationOptions` DTO. `max_actions` is clamped to >= 1 with correct singular/plural error messages.
- **Runtime native config on all bridge calls** — Every bridge call (`schedule`, `cancel`, `cancelAll`, `getPending`, `requestPermission`, `checkPermission`) injects `_config` so native code applies settings immediately — even before the first `schedule()` call.
- **iOS: Configurable default sound and max actions** — iOS now reads `default_sound` and `max_actions` from the `_config` bridge parameter, matching Android behavior.
- **Android: Configurable notification channel** — Channel ID, name, and description are now set from PHP config instead of hardcoded Kotlin constants.
- **Android: Configurable tap detection delay** — The `onResume` warm-start tap detection delay is now configurable via `tap_detection_delay_ms`.
- **Android: Configurable navigation replay duration** — The `livewire:navigated` cold-start replay window is now configurable via `navigation_replay_duration_ms`.
- **Android: Runtime `max_actions` config** — Android now reads `max_actions` from the `_config` bridge parameter instead of using a hardcoded constant.

## [1.3.5] - 2026-03-16

### Fixed

- **Android: Action buttons not visible on Samsung One UI** — Samsung hides notification action buttons when no explicit expanded style is set. Now applies `BigTextStyle` as a fallback when actions are present but no image or `bigText` was configured. Affects all Samsung One UI devices.

## [1.3.4] - 2026-03-16

### Fixed

- **Android: Warm-start notification tap now reliably dispatches NotificationTapped** — Replaced the `localnotification://` URI + lifecycle listener approach (which caused 404 errors in NativePHP's WebView router) with a SharedPreferences-based tap detection mechanism. When a notification is shown, a tap payload is stored. On user dismiss (swipe), a `deleteIntent` clears it. On user tap (auto-cancel), the payload persists. The next bridge call compares stored payloads against `NotificationManager.getActiveNotifications()` and dispatches `NotificationTapped` for any notification that is no longer in the status bar. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Immediate warm-start tap detection via onResume** — Registered an `Application.ActivityLifecycleCallbacks` that runs `detectTappedNotifications()` on every `onResume` with a 500ms delay. Previously, warm-start taps were only detected on the next bridge function call (e.g., clicking a button). Now the event fires automatically when the app returns to the foreground. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Cold-start tap event reaches destination page via `livewire:navigated` replay** — When a cold-start tap dispatches `NotificationTapped` during a bridge call, the new Livewire component may not be hydrated yet (especially with `wire:navigate` SPA-like navigation). A one-shot `livewire:navigated` JS listener now replays the event after the navigation completes, ensuring the destination page's `#[OnNative]` handlers receive it. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Cold-start tap payload deduplication** — After dispatching `NotificationTapped` from the activity's launch intent (cold start), the corresponding tap payload is now cleared to prevent a duplicate dispatch from the warm-start detection logic. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Tap payload cleanup on Cancel/CancelAll** — Stored tap payloads are now cleared when notifications are explicitly cancelled, preventing false `NotificationTapped` events for cancelled notifications.

## [1.3.3] - 2026-03-16

### Fixed

- **Android: Warm-start notification tap now dispatches NotificationTapped** — When the app is already running and the user taps a notification, the event was silently lost because `onNewIntent()` doesn't update `activity.intent`. Fixed by setting a `localnotification://tap` data URI on the launch intent and registering a `NativePHPLifecycle` listener for `onNewIntent` that parses the URI and dispatches the event immediately. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Added `FLAG_ACTIVITY_SINGLE_TOP` to notification tap intent** — Ensures `onNewIntent()` is called on the existing activity instead of destroying and recreating it, enabling the lifecycle listener to intercept warm-start taps. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))

## [1.3.2] - 2026-03-16

### Fixed

- **NotificationTapped event not reaching Livewire `#[OnNative]` handlers** — Pending events (including cold-start tap events) are now flushed from **all** bridge functions (`Cancel`, `CancelAll`, `GetPending`, `CheckPermission`), not just `Schedule` and `RequestPermission`. Previously, if the first bridge call after a notification tap was anything other than those two, the queued `NotificationTapped` event was never dispatched. Applies to both Android and iOS. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))
- **Android: Livewire cold-start race condition** — Added a `livewire:init` JS fallback so that events dispatched before Livewire has loaded are re-dispatched when Livewire initializes. The existing one-shot `if (window.Livewire)` check in `NativeActionCoordinator` silently drops events on cold start; the fallback listens for Livewire's deterministic `livewire:init` lifecycle event and replays the dispatch. ([#10](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues/10))

## [1.3.1] - 2026-03-13

### Added

- **README: Laravel event listener docs** — New "Listening to Events (Laravel)" section documenting standard Laravel event listeners for native UI (EDGE) and non-Livewire stacks. Includes listener class example and EventServiceProvider registration.
- **Boost guidelines: All 3 event listening approaches** — Updated events section to document Livewire `#[OnNative]`, Laravel listeners, and JavaScript `On()` side by side.

## [1.3.0] - 2026-03-13

### Added

- **JavaScript client library** — `resources/js/index.js` now exports named functions (`schedule`, `cancel`, `cancelAll`, `getPending`, `requestPermission`, `checkPermission`) for apps using Inertia with Vue or React. Includes CSRF token handling and error unwrapping matching the official NativePHP `BridgeCall` contract.
- **JavaScript event constants** — `Events` export with all 6 event name constants for use with the NativePHP `On()` listener in SPA frameworks.
- **Boost guidelines** — `resources/boost/guidelines/core.blade.php` documenting facade methods, DTOs, events, JS usage, and common patterns for AI-assisted development.
- **README: JavaScript usage section** — Complete examples for scheduling, cancelling, and listening to events from Vue/React components.
- **README: Event listener docs for SPA** — Shows how to use `On()` from `#nativephp` with the plugin's `Events` constants.
- **README: Required permissions section** — Documents all Android permissions and iOS requirements.

### Changed

- **README: Installation** — Updated to use `php artisan native:plugin:register` as the primary registration method, aligned with NativePHP v3 docs.
- **README: Section headings** — Renamed "Usage" to "Usage (PHP)" and "Listening to Events" to "Listening to Events (Livewire)" for clarity alongside new JS sections.

## [1.2.1] - 2026-03-12

### Fixed

- **Android: Tapping notification now opens the app** — Replaced `PendingIntent.getBroadcast()` with `PendingIntent.getActivity()` for the notification `contentIntent`. On Android 12+ (API 31), `startActivity()` from a `BroadcastReceiver` is silently blocked by the system. The notification tap now launches the app's main activity directly via the OS, bypassing the restriction entirely.
- **Android: NotificationTapped event delivered on cold start** — `dispatchPendingEvents()` now reads tap data from the activity's intent extras (set by `PendingIntent.getActivity()`) and dispatches the `NotificationTapped` event. Intent action is cleared after dispatch to prevent re-delivery on configuration changes.
- **iOS: NotificationTapped event lost on cold start** — Added a pending event queue to `LocalNotificationDelegate`. When `LaravelBridge.shared.send` is nil (bridge not yet initialized during cold start), events are queued in memory via `sendOrQueue()`. Queued events are flushed on the first bridge call (`Schedule` or `RequestPermission`).

### Thanks

- **GMDev (gabyydev)** — for reporting the notification tap issue from the NativePHP Discord community. Community feedback like this helps make the plugin better for everyone.

## [1.2.0] - 2026-03-11

### Added

- **Monthly & Yearly repeat intervals** — `RepeatInterval::Monthly` and `RepeatInterval::Yearly` enum cases. Android uses `Calendar.add()` for variable month lengths and leap years. iOS uses `UNCalendarNotificationTrigger` with appropriate date components ([Epic #5 Phase 1](docs/epics/05-custom-repeat-intervals.md))
- **Custom repeat intervals** — `repeatIntervalSeconds` parameter for any repeat interval >= 60 seconds, mutually exclusive with `repeat` ([Epic #5 Phase 2](docs/epics/05-custom-repeat-intervals.md))
- **Day-of-week scheduling** — `repeatDays` parameter accepts an array of ISO weekdays (1=Monday through 7=Sunday). Creates sub-alarms per day with automatic aggregation in `getPending()` and cleanup in `cancel()` ([Epic #5 Phase 3](docs/epics/05-custom-repeat-intervals.md))
- **Repeat count limit** — `repeatCount` parameter limits how many times a notification repeats. Android decrements in `rescheduleNext()`, iOS tracks via UserDefaults ([Epic #5 Phase 4](docs/epics/05-custom-repeat-intervals.md))
- **`NotificationOptions` DTO** — Type-safe readonly class for scheduling notifications with IDE autocompletion and built-in validation
- **`NotificationAction` DTO** — Type-safe readonly class for action button definitions
- **`LocalNotificationsInterface`** — Interface contract for the main class, enabling dependency injection and custom implementations
- **`NotificationValidator`** — Shared validation class eliminating duplication between DTO and raw array code paths

### Changed

- **Code quality tooling** — Added Laravel Pint (code style), Rector (automated refactoring), and expanded CI pipeline with lint and refactor checks
- **Extensible architecture** — `LocalNotifications` class now uses `protected` methods (`call()`, `normalizeOptions()`) for subclass extensibility
- **ServiceProvider** — Binds `LocalNotificationsInterface` as singleton with alias for concrete class

### Fixed

- **Security: Image URL validation** — Both Android and iOS now reject non-http/https image URLs to prevent SSRF attacks via `file://` or other schemes
- **Android: BroadcastReceiver lifetime** — `LocalNotificationReceiver` now uses `goAsync()` to prevent the system from killing the receiver during image downloads
- **Android: SharedPreferences thread safety** — Added `synchronized` blocks around all SharedPreferences read-modify-write operations to prevent data loss from concurrent access

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

[1.4.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.5...v1.4.0
[1.3.5]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.4...v1.3.5
[1.3.4]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.3...v1.3.4
[1.3.3]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/releases/tag/v1.0.0
