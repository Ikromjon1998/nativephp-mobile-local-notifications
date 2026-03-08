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

- [ ] `NotificationReceived` fires on both iOS and Android when a notification is delivered
- [ ] `NotificationTapped` fires on both iOS and Android when the user taps a notification
- [ ] Custom `data` payload is correctly passed through in both events
- [ ] Events work whether the app is in foreground, background, or killed state
