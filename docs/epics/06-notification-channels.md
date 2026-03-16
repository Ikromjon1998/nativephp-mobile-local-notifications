# Epic 6: Notification Channels & Categories

**Priority:** High
**Status:** Partial — Default channel is configurable via publishable config (v1.4.0). Multi-channel API not yet implemented.

## Description

Allow developers to create and manage notification channels (Android) and categories (iOS) for different notification types. Currently, all notifications use a single default channel whose ID, name, description, and importance can be customized via `config/local-notifications.php`.

## What's Done (v1.4.0)

- [x] Publishable `config/local-notifications.php` with `channel_id`, `channel_name`, `channel_description`
- [x] ServiceProvider merges config and supports `php artisan vendor:publish --tag=local-notifications-config`
- [x] PHP config values flow to Android at runtime via `_config` bridge parameter
- [x] `ensureNotificationChannel()` creates/updates the channel idempotently (name/description updates work)
- [x] `saveNotificationInfo()` persists `channelId` so BootReceiver restores notifications on the correct channel
- [x] BootReceiver reads stored `channelId` with fallback to default
- [x] Default channel works when no custom config is published

## Remaining Scope

- Add new `createChannel()` method to create additional notification channels with configurable: `importance` (low/default/high/urgent), `sound`, `vibration`, `lights`, and `badge`
- Add `deleteChannel()` and `listChannels()` methods
- Add optional `channelId` parameter to the `schedule()` options to assign a notification to a specific channel (currently all notifications use the single configured default channel)
- **Android:** Map directly to `NotificationChannel` API. Each channel gets its own importance level, sound settings, and vibration pattern
- **iOS:** Map to `UNNotificationCategory`. While iOS doesn't have channels in the Android sense, categories control the actions and display options. Use `threadIdentifier` for grouping
- Add `setDefaultChannel()` method to change the default channel at runtime

## Acceptance Criteria

- [x] Default channel is configurable via publishable config file
- [x] Channel name/description updates take effect without reinstalling
- [ ] Developers can create multiple channels with different settings
- [ ] Notifications can be assigned to specific channels via `channelId` parameter
- [ ] Android channels appear in system notification settings
- [ ] iOS categories group notifications correctly
- [ ] `createChannel()`, `deleteChannel()`, `listChannels()` methods work on both platforms
