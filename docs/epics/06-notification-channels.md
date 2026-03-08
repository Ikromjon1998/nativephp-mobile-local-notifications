# Epic 6: Notification Channels & Categories

**Priority:** High
**Status:** Not Started

## Description

Allow developers to create and manage notification channels (Android) and categories (iOS) for different notification types. Currently, all notifications use a single hardcoded channel with fixed importance and settings.

## Scope

- Add new `createChannel()` method to create a notification channel with configurable: `id`, `name`, `description`, `importance` (low/default/high/urgent), `sound`, `vibration`, `lights`, and `badge`
- Add `deleteChannel()` and `listChannels()` methods
- Add optional `channelId` parameter to the `schedule()` options to assign a notification to a specific channel
- **Android:** Map directly to `NotificationChannel` API. Create channels via `NotificationManager.createNotificationChannel()`. Each channel gets its own importance level, sound settings, and vibration pattern
- **iOS:** Map to `UNNotificationCategory`. While iOS doesn't have channels in the Android sense, categories control the actions and display options. Use `threadIdentifier` for grouping
- If no channel is specified, use a sensible default channel (current behavior)
- Add `setDefaultChannel()` method to change the default channel globally

## Acceptance Criteria

- [ ] Developers can create multiple channels with different settings
- [ ] Notifications can be assigned to specific channels
- [ ] Android channels appear in system notification settings
- [ ] iOS categories group notifications correctly
- [ ] Default channel works when no channel is specified
