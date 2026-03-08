# Epic 7: Notification Update & Modification

**Priority:** Medium
**Status:** Not Started

## Description

Allow updating an already-scheduled notification without canceling and rescheduling manually. Currently, modifying a notification requires a cancel + reschedule, which is error-prone and loses the original timing context.

## Scope

- Add `update(string $id, array $options)` method that modifies a pending notification's properties (title, body, data, etc.) while preserving its schedule
- If only display properties change (title, body, data), update the stored metadata without touching the alarm/trigger
- If timing properties change (delay, at, repeat), cancel the old alarm and create a new one with the updated schedule
- **Android:** Update the notification info in SharedPreferences. If the notification has already been delivered (non-repeating), update the displayed notification via `NotificationManager.notify()` with the same ID
- **iOS:** Remove the old request and add a new one with the same identifier but updated content/trigger
- Add `NotificationUpdated` event class dispatched after successful update
- Return error if the notification ID doesn't exist in pending notifications

## Acceptance Criteria

- [ ] Updating a pending notification preserves its trigger time when only content changes
- [ ] Updating timing properties correctly reschedules the notification
- [ ] Updating an already-delivered notification updates its display on Android
- [ ] Error returned for non-existent notification IDs
- [ ] `NotificationUpdated` event fires on success
