# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11.0] - Unreleased

**Minor release — native code changed.** Per NativePHP's versioning policy, apps must rebuild with `php artisan native:install --force` after updating.

### Added

- **Notification priority** — New `priority` option (`low`, `default`, `high`, `urgent`; `NotificationPriority` enum or strings) mapping to Android channel importance (with dedicated per-priority channels) and iOS interruption levels (`.passive`/`.active`/`.timeSensitive`/`.critical`) plus `relevanceScore`. Omitting `priority` keeps the pre-1.11 high-importance behavior.
- **Silent delivery** — `silent: true` delivers without sound or vibration (on Android also without the heads-up banner). Independent of priority; preserved across repeats, snoozes, reboots, and updates.
- **iOS critical-alert entitlement detection** — `urgent` uses `.critical` only when the app holds Apple's critical-alerts entitlement (checked via `criticalAlertSetting` at schedule time, since iOS silently degrades unentitled requests instead of erroring); otherwise it downgrades to `.timeSensitive` automatically.
- **`requestPermission(critical: true)`** — opt-in request for iOS critical-alert authorization. Without it `criticalAlertSetting` never becomes enabled and `urgent` could never actually deliver as `.critical`, even in entitled apps. Ignored on Android; only call it when the app holds the critical-alerts entitlement. Note for custom implementors: `LocalNotificationsInterface::requestPermission()` gained the `bool $critical = false` parameter, so classes implementing the interface must add it.
- **Reserved `data` keys documented** — `notification_id`, `sound`, `soundName`, `priority`, `silent`, and `action_snooze` are used internally and must not be used as custom `data` keys (`sound` is newly reserved in this release to persist the sound flag across updates on iOS).
- **Reserved id validation** — notification ids ending in `_snooze` or `_day_{1-7}` are now rejected: those suffixes are reserved for internal sub-notifications, and colliding ids were silently cancelled or reported under the wrong id.
- **`silent` is validated as a boolean** — a non-bool truthy value (e.g. `1`) previously passed through and behaved oppositely per platform (silent on iOS, audible on Android).

### Fixed

- **Snooze press no longer risks crashing the app (Android)** — the snooze reschedule now goes through the shared scheduler, which falls back to an inexact alarm when the user has revoked the "Alarms & reminders" permission (Android 12+) instead of throwing an uncaught `SecurityException` in the broadcast receiver.
- **`update()` now reaches snoozed deliveries** — a pending `{id}_snooze` side-alarm/request previously kept its delivery-time content, and updating a fired-then-snoozed one-shot returned "Notification not found". Both platforms now refresh the snoozed delivery with the updated content while preserving its remaining snooze countdown (timing options are ignored when only a snooze is pending).
- **Day-of-week snooze reports the original id (Android)** — snoozing a `repeatDays` occurrence leaked the internal `{id}_day_N` sub-id through `getPending()` and event payloads (iOS already reported the parent id); cancelling with that leaked id could destroy the real weekly sub-alarm. Snoozes now always attach to the parent id, and event payloads strip both internal suffixes.
- **Android: updating a visible notification keeps it functional** — the refreshed notification previously lost its tap intent, action buttons, and dismiss handling (tapping did nothing). The refresh now reuses the same builder as fresh deliveries.
- **iOS: un-silencing via `update(silent: false)` restores the default sound** — the sound flag is now persisted in `userInfo`; previously it was derived from the content, which is soundless while silent, so the sound was permanently lost.
- **Android: action buttons keep their snooze duration after `update()`** — merging stored actions dropped the `snooze` field, turning snooze buttons into plain buttons on the next delivery.

- **Snoozing a repeating notification no longer kills its repeat chain** — on both platforms, snooze reused the original alarm/request identity: on Android the snooze PendingIntent overwrote the pending repeat alarm (and its post-fire cleanup erased the persisted repeat entry, breaking reboot recovery too); on iOS re-adding with the same identifier replaced the pending repeating request. Snoozes are now separate one-shot `{id}_snooze` side-alarms: repeat chains are untouched, events report the original id, `getPending()` lists the snooze with `snoozed: true`, `cancel()` covers it, and on Android it is persisted for reboot recovery. Thanks to Zeshan Ziya ([@zeshanziya](https://github.com/zeshanziya)) for finding the bug and the core of the Android fix.
- **iOS: snoozing a silent notification stays silent** — the snoozed delivery no longer force-defaults the sound when the notification was scheduled with `silent: true`.
- **Android: updating an active notification no longer re-alerts** — the rebuilt notification uses `setOnlyAlertOnce(true)` and reuses the original channel, so updating a silent notification stays silent.
- **Unknown priority values are normalized away** — values that bypass PHP validation (e.g. sent directly from the JS bridge) fall back to the legacy no-priority behavior on both platforms instead of creating junk notification channels.

### Documentation

- iOS entitlement requirements for `high`/`urgent` (Time Sensitive Notifications capability; Apple-granted critical-alerts entitlement), omitted-vs-`default` priority semantics, Android per-priority channel visibility, and a v1.11.0 upgrade guide.

## [1.10.1] - 2026-08-14

### Added

- **Laravel 13 support** — Widened the `illuminate/support` constraint to `^11.0||^12.0||^13.0` so the package installs on Laravel 13 apps instead of silently resolving to v1.9.0 (#22). Laravel 13 requires `nativephp/mobile` v3.3.7+. Following NativePHP's versioning policy, this is a patch release: it only changes Laravel/PHP code, contains no native code changes, and requires no app rebuild.
- **CI matrix across Laravel versions** — Tests now run against Laravel 11, 12, and 13 on PHP 8.3 and 8.4, so every supported combination is verified on each push. Because Laravel 11 is past its security-fix EOL, Composer 2.9+ would refuse to resolve it; this repo's own installs ignore `laravel/framework` advisories via `config.policy.advisories.ignore` (root-only config — it has no effect on apps that install this package).

### Changed

- **Refreshed AI assistant guidelines (Boost)** — `resources/boost/guidelines/core.blade.php` was stuck at the v1.4-era API. It now documents the Laravel notification channel (`LocalNotificationChannel` / `LocalNotificationMessage`), snooze actions, custom sounds (`soundName` and the `resources/sounds/` convention), the full JavaScript export list, and the supported PHP/Laravel/NativePHP versions.

### Fixed

- **Bridge calls with unencodable payloads** — Payloads that cannot be JSON-encoded are now guarded against instead of failing silently at the bridge boundary.
- **Leftover debug log** — Removed a stray type-debugging log statement from `parseParams`.

## [1.10.0] - 2026-04-07

### Added

- **Type-safe enums** — Introduced dedicated enums and validation for notification parameters instead of loose strings.
- **Shared native `Config` utility** — Extracted a shared Config utility on Android, deduplicated `toArray()`, and centralized intent keys and repeat types; iOS constants and keys were centralized the same way.

### Fixed

- **Android lifecycle leak and resource cleanup** — Hardened receivers with proper cleanup, an alarm fallback path, and consistent logging.
- **Crash-safe JSON parsing** — All `JSONObject(dataJson)` calls across Android receivers are wrapped in try/catch.
- **Collision-safe IDs** — Notification IDs are generated with `Str::uuid()` (with an explicit `illuminate/support` dependency) instead of ad-hoc values.

## [1.9.0] - 2026-04-05

### Added

- **Custom notification sounds** — Optional `soundName` parameter accepts a filename (e.g. `"alert.wav"`) to play a custom sound instead of the system default. Works with both the array API (`'soundName' => 'alert.wav'`) and the fluent builder (`->sound('alert.wav')` or `->soundName('alert.wav')`).
- **Android per-sound notification channels** — Custom sounds automatically create a dedicated notification channel (`{channel_id}_sound_{name}`) since Android O+ requires sound to be set on the channel. Falls back to the default channel if the sound resource is not found.
- **iOS named sounds** — Uses `UNNotificationSound(named:)` for custom sound files bundled in the app.
- **Sound filename validation** — Validates that `soundName` is a proper filename with extension (alphanumeric, hyphens, underscores only). Rejects paths and extensionless names.

## [1.8.0] - 2026-04-05

### Added

- **Native snooze rescheduling** — Action buttons can now include a `snooze` parameter (seconds) that reschedules the notification natively via AlarmManager (Android) or UNTimeIntervalNotificationTrigger (iOS). Works even when the app is killed — no app launch required.
- **`snooze` property on `NotificationAction`** — `new NotificationAction('snooze', 'Snooze', snooze: 300)` or fluent `->action('snooze', 'Snooze', snooze: 300)` for 5-minute snooze.
- **Navigation replay for queued pending events** — Cold-start action button events now include `injectNavigationReplay` so they appear in Livewire components after page navigation.

### Fixed

- **Cold-start action button events not showing in logs** — Added navigation replay for queued pending events so they're dispatched on both initial load and subsequent `livewire:navigated` events.
- **Removed `startActivity()` from `NotificationActionReceiver`** — The call failed silently on Android 12+ (API 31) and is unnecessary. Action button presses now store pending events that flush when the user manually opens the app. Snooze actions reschedule natively without needing the app.

## [1.7.1] - 2026-04-05

### Fixed

- **Cold-start init component now works with all frontend stacks** — Previously only flushed pending events for Livewire apps (`livewire:navigated`). Now also flushes after `window.load` for Inertia/Vue/React and plain JS apps. Uses a guard flag to prevent double-flushing.
- **Fixed Livewire event handler examples in README** — Changed from incorrect `$data['id']` single-array pattern to named parameters (`string $id, string $title, string $body`) which is how Livewire 3 and 4 actually dispatch event payloads.
- **Added event payload keys reference table** in README for all 7 events.

## [1.7.0] - 2026-04-05

### Added

- **Laravel Notification channel integration** — Use the standard `$user->notify()` pattern with `LocalNotificationChannel` and fluent `LocalNotificationMessage` builder. Implements `toLocalNotification()` alongside existing Facade usage.
- **`LocalNotificationMessage`** — Fluent message builder with chainable methods for all notification options (title, body, subtitle, delay, at, repeat, sound, badge, data, image, bigText, action buttons).
- **`LocalNotificationChannel`** — Laravel Notification channel that sends via the existing `LocalNotifications` service.
- **`HasLocalNotification` interface** — Type-safe contract for notifications that implement `toLocalNotification()`.

### Fixed

- **Android action buttons not appearing** — Fixed type coercion bug where NativePHP's bridge could pass actions as `JSONArray` or `String` instead of `List<*>`, causing `as? List<*>` safe cast to silently return `null`. Added `coerceToList()` helper that handles `List`, `JSONArray`, and JSON string types. Also fixed `serializeActions()` to handle `JSONObject` elements alongside `Map` elements.
- **Added debug logging for action buttons** — `LocalNotificationReceiver` and `NotificationScheduler` now log action type info, serialized data, and parse results for easier debugging via `adb logcat`.

## [1.6.0] - 2026-04-04

### Added

- **`update()` method** — Update an existing scheduled notification's content or timing without manual cancel + reschedule. Content-only updates preserve the original trigger; timing changes automatically reschedule.
- **`NotificationUpdated` event** — Dispatched after a successful update, with `id`, `title`, and `body` payload.
- **Android `Update` bridge function** — Merges new parameters with stored notification info in SharedPreferences, handles both single and day-of-week notifications.
- **iOS `Update` bridge function** — Looks up pending UNNotificationRequests by ID, merges content, and replaces the request.
- **JS `update()` function** — `update(id, options)` for Inertia/Vue/React apps.
- **`NotificationUpdated` JS event constant** — `Events.NotificationUpdated` for use with the NativePHP `On()` listener.

## [1.5.1] - 2026-04-04

### Changed

- **Aligned min_version with NativePHP Mobile baseline** — Updated `nativephp.json` to Android API 29 and iOS 18.0. NativePHP enforces these as the minimum at build time regardless of plugin declarations, so the previous values (Android 26, iOS 16.0) were cosmetic and misleading.
- **Closed Epic 14 (Lower OS Requirements) as N/A** — Plugin min_versions cannot go below the NativePHP framework baseline.

## [1.5.0] - 2026-04-04

### Changed

- **Lowered iOS minimum version from 18.2 to 16.0** — All notification APIs used are available since iOS 10. The previous 18.2 requirement unnecessarily excluded most devices.
- **Renamed internal Android action strings** — Changed `com.ikromjon.localnotifications.*` intent actions and Kotlin package to `com.nativephp.localnotifications.*` for consistency with the NativePHP ecosystem. This is an internal change with no impact on the public PHP API.
- **Renamed iOS DispatchQueue label** — Changed from `com.ikromjon.localnotifications.pending` to `com.nativephp.localnotifications.pending`.
- **Updated `nativephp.json` bridge function references** — Android bridge function paths now use `com.nativephp.localnotifications` package.

### Added

- **Quick Start section in README** — Concise example showing the simplest path to scheduling a notification.

### Fixed

- **CONTRIBUTING.md** — Removed hardcoded reference to the Daily Habits example app in the testing section.
- **ROADMAP.md** — Updated Epic 6 (Channels) status to "Partial" and Epic 14 (Lower OS Requirements) to "Done" to reflect current state.

## [1.4.1] - 2026-03-19

### Fixed

- **Init component fires after Livewire hydration** — Changed `<x-local-notifications::init />` to flush on `livewire:navigated` (after components are hydrated) instead of `DOMContentLoaded` (before components are ready). Previously, the cold-start tap event was dispatched via JS injection before Livewire components had registered their event listeners, causing the event to be silently lost on the initial page load. The event was only received after navigating away and back (via the `livewire:navigated` replay). Now the flush triggers 300ms after `livewire:navigated`, ensuring components are ready to receive the event immediately.
- **Removed unnecessary CSRF header from init component** — The `/_native/api/call` endpoint is intercepted by the native bridge, not Laravel's web stack, so the CSRF token is not needed.

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

## [1.0.0] - 2026-03-08

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

[1.4.1]: https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/compare/v1.4.0...v1.4.1
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
