# Epic 12: Notification Badge Management

**Priority:** Low
**Status:** Not Started

## Description

Extend badge support beyond the current iOS-only integer badge to provide cross-platform badge management. Currently, the `badge` parameter only works on iOS, and there are no methods to read or manipulate the badge count independently of notifications.

## Scope

- Add `getBadgeCount()` method to retrieve the current badge number
- Add `setBadgeCount(int $count)` method to set the badge without scheduling a notification
- Add `clearBadge()` method (shorthand for `setBadgeCount(0)`)
- Add `incrementBadge(int $by = 1)` method for atomic badge increment
- **iOS:** Use `UNUserNotificationCenter.setBadgeCount()` (iOS 16+) for direct badge management. Continue supporting `badge` in notification content for automatic badge on delivery
- **Android:** Use `ShortcutBadger` library or the platform `NotificationCompat.Builder.setNumber()` for launcher badge support. Note: Android badge support varies by launcher — Samsung, Huawei, Xiaomi all have different APIs. Consider using the notification count as the badge on stock Android 8+
- Auto-decrement badge when `NotificationTapped` fires (optional, configurable)
- Document platform differences in badge behavior

## Acceptance Criteria

- [ ] Badge count can be read, set, cleared, and incremented on both platforms
- [ ] Badge updates reflect on the app icon
- [ ] Auto-decrement on tap works when enabled
- [ ] Documentation clearly states platform-specific behavior and limitations
