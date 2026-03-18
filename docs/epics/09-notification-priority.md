# Epic 9: Notification Priority & Importance Control

**Priority:** Medium
**Status:** Not Started

## Prerequisites

- Epic 6 (Notification Channels) — v1.4.0 made the default channel configurable. Priority control on Android requires separate channels per importance level, which is part of Epic 6's remaining scope.

## Description

Allow developers to control the urgency and display behavior of individual notifications. Currently, the default channel uses `IMPORTANCE_HIGH` (configurable in v1.4.0 via `config/local-notifications.php` channel settings) with no per-notification priority control.

## Scope

- Add optional `priority` parameter to schedule options with values: `low`, `default`, `high`, `urgent`
- **Android:** Map priority to notification importance levels. `urgent` = heads-up notification with `PRIORITY_HIGH` + `IMPORTANCE_HIGH`, `low` = `PRIORITY_LOW` + `IMPORTANCE_LOW` (no sound, appears in shade only). Consider creating separate channels per priority level since Android ties importance to channels, not individual notifications
- **iOS:** Map to `interruptionLevel` property on `UNMutableNotificationContent`: `low` = `.passive`, `default` = `.active`, `high` = `.timeSensitive`, `urgent` = `.critical` (requires special entitlement). Set `relevanceScore` based on priority for notification summary ranking
- Add optional `silent` boolean parameter — when true, deliver notification without sound or vibration regardless of other settings (useful for background data updates)
- Handle the case where critical notifications (urgent on iOS) require a special entitlement — fall back to `.timeSensitive` if the entitlement is not available

## Acceptance Criteria

- [ ] Each priority level produces visually distinct notification behavior on both platforms
- [ ] `urgent` notifications appear as heads-up/banner notifications
- [ ] `low` notifications appear silently in the notification shade
- [ ] `silent` parameter works independently of priority
- [ ] Graceful fallback when iOS critical notification entitlement is unavailable
