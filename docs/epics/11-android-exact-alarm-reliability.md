# Epic 11: Android Exact Alarm Reliability

**Priority:** Medium
**Status:** Partial — Core features done, optional enhancements remain

## Description

Improve notification delivery timing accuracy on Android, especially for repeating notifications and devices with aggressive battery optimization. Currently, repeating notifications use `AlarmManager.setRepeating()` which is inexact on modern Android.

## Scope

- Replace `AlarmManager.setRepeating()` with `setExactAndAllowWhileIdle()` + manual rescheduling pattern for repeating notifications. After each delivery in `LocalNotificationReceiver`, calculate the next trigger time and schedule a new exact alarm
- Handle the `SCHEDULE_EXACT_ALARM` permission properly for Android 12+ (API 31+): check `canScheduleExactAlarms()` before scheduling, and guide the user to grant the permission in system settings if denied
- Add `AlarmManager.ACTION_SCHEDULE_EXACT_ALARM_PERMISSION_STATE_CHANGED` broadcast receiver to detect when the user grants/denies exact alarm permission
- Implement a `WorkManager` fallback for devices where exact alarms are not available — use `OneTimeWorkRequest` with initial delay as a best-effort alternative
- Handle Doze mode: use `setAlarmClock()` for the highest-priority notifications as it bypasses Doze restrictions entirely (shows alarm icon in status bar)
- Improve `BootReceiver` to verify all rescheduled alarms are actually set by checking `AlarmManager.getNextAlarmClock()`
- Add logging/telemetry events for missed or late notifications to help developers debug delivery issues

## Acceptance Criteria

- [x] Repeating notifications use `setExactAndAllowWhileIdle()` + self-rescheduling pattern (replaces `setRepeating()`)
- [x] Notifications survive Doze mode and battery optimization
- [ ] Graceful degradation on devices that don't allow exact alarms (`WorkManager` fallback)
- [x] Boot receiver successfully restores all notifications
- [ ] Developer-facing events for delivery diagnostics (logging/telemetry)
