# Epic 2: Fix Missing Event Dispatches

**Priority:** Critical
**Status:** Done

## Description

The `NotificationReceived` and `NotificationTapped` event classes exist in PHP but are never actually dispatched from the native code on one or both platforms. This means developers cannot react to notification delivery or user interaction, which is a core expected feature.

## Scope

- **Android `NotificationReceived`:** In `LocalNotificationReceiver.kt`, after the notification is displayed via `NotificationManager.notify()`, dispatch the `NotificationReceived` event back to the PHP layer with `id`, `title`, `body`, and `data` payload
- **Android `NotificationTapped`:** When the user taps a notification and the app opens via the content intent, detect the notification extras in the activity and dispatch the `NotificationTapped` event with the full payload including custom `data`
- **iOS `NotificationReceived`:** Implement `UNUserNotificationCenterDelegate.userNotificationCenter(_:willPresent:)` to dispatch `NotificationReceived` when a notification is delivered while the app is in the foreground, and ensure background delivery also fires the event
- **iOS `NotificationTapped`:** Implement `UNUserNotificationCenterDelegate.userNotificationCenter(_:didReceive:)` to dispatch `NotificationTapped` when the user taps a notification, including the full `userInfo` data payload
- Ensure all event payloads are consistent across platforms (`id`, `title`, `body`, `data`)

## Acceptance Criteria

- [x] `NotificationReceived` fires on both iOS and Android when a notification is delivered
- [x] `NotificationTapped` fires on both iOS and Android when the user taps a notification
- [x] Custom `data` payload is correctly passed through in both events
- [x] Events work whether the app is in foreground, background, or killed state

## Follow-up Fix (v1.2.1)

The original implementation had two remaining issues discovered during real-device testing:

1. **Android:** `PendingIntent.getBroadcast()` was used for the notification `contentIntent`, but `startActivity()` from a `BroadcastReceiver` is silently blocked on Android 12+ (API 31). Fixed by using `PendingIntent.getActivity()` to launch the app directly.
2. **iOS:** `LaravelBridge.shared.send?()` optional chaining silently dropped `NotificationTapped` events during cold start when the bridge was nil. Fixed by adding a `sendOrQueue()` pending event queue with `dispatchPendingEvents()` flush on first bridge call.
