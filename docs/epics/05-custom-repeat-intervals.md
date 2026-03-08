# Epic 5: Custom Repeat Intervals & Advanced Scheduling

**Priority:** High
**Status:** Not Started

## Description

Expand the rigid 4-option repeat system (`minute`, `hourly`, `daily`, `weekly`) to support flexible scheduling patterns. Developers need custom intervals, specific day/time scheduling, and repeat limits.

## Scope

- Add `repeatInterval` parameter accepting an integer (seconds) for custom repeat intervals (e.g., every 2 hours = 7200)
- Add `repeatDays` parameter accepting an array of day numbers (1=Monday through 7=Sunday) for weekly scheduling on specific days
- Add `repeatTime` parameter (HH:mm format string) to fire at a specific time of day when combined with `daily` or `weekly` repeat
- Add `monthly` and `yearly` repeat options
- **iOS:** Use `UNCalendarNotificationTrigger` with appropriate `DateComponents` for all calendar-based schedules
- **Android:** For exact custom intervals, use `setExactAndAllowWhileIdle()` with manual rescheduling in the receiver instead of `setRepeating()` to guarantee precision. After each delivery, calculate and schedule the next occurrence
- Add `repeatCount` parameter to limit the number of repetitions (e.g., repeat 5 times then stop)
- Deprecate the old string-based `repeat` parameter in favor of the new system while maintaining backward compatibility

## Acceptance Criteria

- [ ] Custom second-based intervals work on both platforms
- [ ] Day-of-week scheduling fires on correct days
- [ ] Time-of-day scheduling fires at the correct time
- [ ] Monthly/yearly repeats work correctly including edge cases (Feb 29, etc.)
- [ ] Repeat count limits are enforced
- [ ] Old `repeat` parameter still works for backward compatibility
