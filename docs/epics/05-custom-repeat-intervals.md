# Epic 5: Custom Repeat Intervals & Advanced Scheduling

**Priority:** High
**Status:** In Progress

## Description

Expand the rigid 4-option repeat system (`minute`, `hourly`, `daily`, `weekly`) to support flexible scheduling patterns. Developers need custom intervals, specific day/time scheduling, monthly/yearly repeats, and repeat limits.

## Current State

The following scheduling features are already implemented across all layers:

### PHP Layer (`src/`)
- `RepeatInterval` enum with 6 cases: `Minute`, `Hourly`, `Daily`, `Weekly`, `Monthly`, `Yearly`
- `schedule()` accepts `repeat` as either `RepeatInterval` enum or string
- `delay` (seconds from now) and `at` (Unix timestamp) for trigger timing
- No validation — parameters pass through to native layer

### Android (`resources/android/src/`)
- `LocalNotificationsFunctions.Schedule` maps repeat strings to milliseconds:
  - `"minute"` → 60,000ms, `"hourly"` → 3,600,000ms, `"daily"` → 86,400,000ms, `"weekly"` → 604,800,000ms, `"monthly"` → calendar-based, `"yearly"` → calendar-based
- Uses `setExactAndAllowWhileIdle()` for all alarms (Epic 11 work)
- `LocalNotificationReceiver` self-reschedules repeating notifications via `repeat_ms` intent extra
- `BootReceiver` restores alarms from SharedPreferences on reboot
- Notifications persisted with `repeatMs` and `repeatType` fields

### iOS (`resources/ios/Sources/`)
- `LocalNotificationsFunctions.Schedule` uses `UNCalendarNotificationTrigger` with repeat:
  - `"minute"` → matches `[.second]` components
  - `"hourly"` → matches `[.minute, .second]` components
  - `"daily"` → matches `[.hour, .minute, .second]` components
  - `"weekly"` → matches `[.weekday, .hour, .minute, .second]` components
  - `"monthly"` → matches `[.day, .hour, .minute, .second]` components
  - `"yearly"` → matches `[.month, .day, .hour, .minute, .second]` components
- Falls back to `UNTimeIntervalNotificationTrigger` for delay-based scheduling
- Unknown repeat strings silently set `repeats = false`

### Tests (`tests/`)
- All 6 `RepeatInterval` enum cases tested for string conversion (including Monthly, Yearly)
- Dedicated tests for Monthly and Yearly enum-to-string conversion
- String repeat values tested for passthrough

## Scope

### Phase 1: Monthly & Yearly Repeats
- Add `Monthly` and `Yearly` cases to `RepeatInterval` enum
- **iOS:** Use `UNCalendarNotificationTrigger` with `[.day, .hour, .minute, .second]` for monthly and `[.month, .day, .hour, .minute, .second]` for yearly
- **Android:** Calculate next occurrence in `LocalNotificationReceiver.rescheduleNext()` using `Calendar` to handle variable month lengths and leap years
- Store the original repeat type string (not just `repeatMs`) in SharedPreferences so `BootReceiver` can recalculate correctly

### Phase 2: Custom Second-Based Intervals
- Add `repeatIntervalSeconds` parameter (integer) as an alternative to preset `repeat` values
- Minimum interval: 60 seconds (iOS UNTimeIntervalNotificationTrigger requires ≥60s for repeating)
- **PHP:** Validate minimum in `schedule()`, convert to bridge parameter
- **Android:** Pass as `repeat_ms` (seconds × 1000), existing self-reschedule pattern handles it
- **iOS:** Use `UNTimeIntervalNotificationTrigger(timeInterval:repeats:)` for custom intervals
- `repeat` and `repeatIntervalSeconds` are mutually exclusive — error if both provided

### Phase 3: Day-of-Week Scheduling
- Add `repeatDays` parameter: array of integers (1=Monday through 7=Sunday)
- Requires `at` timestamp to determine the time-of-day to fire
- **iOS:** Create one `UNCalendarNotificationTrigger` per selected day with `[.weekday, .hour, .minute, .second]` — each gets a unique sub-ID (`{id}_day_{weekday}`)
- **Android:** Schedule separate alarms per day; `rescheduleNext()` calculates next matching weekday
- `cancel(id)` must cancel all sub-IDs for multi-day schedules
- `getPending()` must aggregate sub-IDs back into a single logical notification

### Phase 4: Repeat Count Limit
- Add `repeatCount` parameter (integer) to limit the number of repetitions
- **Android:** Store `remainingCount` in SharedPreferences; decrement in `rescheduleNext()`; stop rescheduling when 0
- **iOS:** No native support — must implement via scheduled removals or App Delegate tracking
- `getPending()` should include remaining count

### Phase 5: Deprecation & Backward Compatibility
- Keep the existing `repeat` parameter working as-is (no breaking changes)
- Document new parameters in README
- Add migration guide if the old `repeat` parameter is eventually deprecated

## Acceptance Criteria

- [x] `Monthly` and `Yearly` repeat intervals work on both platforms
- [x] Monthly handles variable month lengths (Jan 31 → Feb 28/29 → Mar 31) — Android uses `Calendar.add(MONTH)`
- [x] Yearly handles Feb 29 for leap years — Android uses `Calendar.add(YEAR)`
- [ ] Custom second-based intervals (≥60s) work on both platforms
- [ ] Minimum interval validation rejects intervals < 60 seconds
- [ ] `repeat` and `repeatIntervalSeconds` cannot be used together
- [ ] Day-of-week scheduling fires on correct days at correct times
- [ ] Cancelling a multi-day notification cancels all sub-alarms
- [ ] `getPending()` returns correct data for all new interval types
- [ ] Repeat count limits are enforced on both platforms
- [ ] Old `repeat` parameter with 4 preset values still works unchanged
- [x] `BootReceiver` restores all new interval types correctly on Android
- [x] Tests cover monthly/yearly enum conversion and backward compatibility
