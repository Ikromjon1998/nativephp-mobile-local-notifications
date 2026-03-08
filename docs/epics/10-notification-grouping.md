# Epic 10: Notification Grouping & Threading

**Priority:** Medium
**Status:** Not Started

## Description

Allow related notifications to be visually grouped together in the notification shade. Without grouping, apps that send multiple notifications clutter the shade with individual entries.

## Scope

- Add optional `groupId` parameter to schedule options to assign a notification to a group
- Add optional `groupSummary` parameter (string) — when provided, this notification acts as the summary for the group
- **Android:** Use `setGroup()` on the notification builder with the provided `groupId`. Create a summary notification using `setGroupSummary(true)` with `InboxStyle` showing a count of notifications in the group. Use `setSortKey()` for ordering within the group
- **iOS:** Set `threadIdentifier` on `UNMutableNotificationContent` to the `groupId`. iOS automatically groups notifications with the same thread identifier. Use `summaryArgument` and `summaryArgumentCount` for the group summary text
- When the first notification in a group fires, automatically create the summary notification on Android (iOS handles this natively)
- Support `groupBehavior` option: `bundled` (default, all grouped) or `individual` (each notification separate even if in a group)

## Acceptance Criteria

- [ ] Notifications with the same `groupId` are visually grouped on both platforms
- [ ] Group summary notification shows correct count and preview text
- [ ] Ungrouped notifications (no `groupId`) continue to work as individual notifications
- [ ] Grouping works with all other features (actions, images, custom sounds)
