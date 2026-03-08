# Epic 4: Action Buttons

**Priority:** High
**Status:** Done

## Description

Allow developers to define interactive action buttons on notifications (e.g., "Reply", "Snooze", "Mark as Done"). Currently, users can only tap the notification itself with no additional interaction options.

## Scope

- Add optional `actions` array parameter to schedule options, where each action has an `id`, `title`, and optional `destructive` (boolean) flag
- **iOS:** Register `UNNotificationCategory` with `UNNotificationAction` items. Assign the category identifier to the notification content. Handle action responses in the `UNUserNotificationCenterDelegate`
- **Android:** Add `NotificationCompat.Action` buttons to the notification builder. Create separate `PendingIntent` for each action pointing to a new `ActionReceiver` broadcast receiver that extracts the action ID
- Create a new `NotificationActionPressed` event class with properties: `notificationId`, `actionId`, and `data`
- Dispatch `NotificationActionPressed` from both platforms when a user taps an action button
- Support a maximum of 3 actions per notification (platform constraint on iOS)
- Add optional `input` flag on actions for text input actions (iOS inline reply, Android `RemoteInput`)

## Acceptance Criteria

- [ ] Up to 3 action buttons render on both platforms
- [ ] Tapping an action button fires the `NotificationActionPressed` event with correct identifiers
- [ ] Text input actions work on both platforms
- [ ] Actions work when the app is in background or killed state
