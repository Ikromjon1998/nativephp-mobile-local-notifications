# Repeat Intervals

All examples on this page assume the following imports:

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
```

Use the `RepeatInterval` enum (recommended) or pass the string value directly:

| Enum | String | Description |
|------|--------|-------------|
| `RepeatInterval::Minute` | `'minute'` | Every minute |
| `RepeatInterval::Hourly` | `'hourly'` | Every hour |
| `RepeatInterval::Daily` | `'daily'` | Every day |
| `RepeatInterval::Weekly` | `'weekly'` | Every week |
| `RepeatInterval::Monthly` | `'monthly'` | Every month (handles variable month lengths) |
| `RepeatInterval::Yearly` | `'yearly'` | Every year (handles leap years) |

## Custom Repeat Interval

Use `repeatIntervalSeconds` for any interval >= 60 seconds:

```php
// Every 2 hours
LocalNotifications::schedule([
    'id' => 'custom-interval',
    'title' => 'Check In',
    'body' => 'Time for a check-in',
    'repeatIntervalSeconds' => 7200,
]);
```

## Day-of-Week Scheduling

Use `repeatDays` to fire on specific days of the week. Days use ISO format: 1=Monday through 7=Sunday. Requires `at` to set the time of day.

```php
// Weekdays at 8:30 AM
LocalNotifications::schedule([
    'id' => 'weekday-alarm',
    'title' => 'Good Morning',
    'body' => 'Time to start the day!',
    'at' => now()->setTime(8, 30)->timestamp,
    'repeatDays' => [1, 2, 3, 4, 5], // Mon-Fri
]);
```

## Repeat Count Limit

Use `repeatCount` to limit how many times a notification repeats:

```php
// Remind 3 times, then stop
LocalNotifications::schedule([
    'id' => 'limited-reminder',
    'title' => 'Take your medicine',
    'body' => 'Time for your dose',
    'repeat' => RepeatInterval::Daily,
    'at' => now()->setTime(20, 0)->timestamp,
    'repeatCount' => 3,
]);
```

## How Repeating Notifications Work

On **iOS**, the system natively supports repeating notifications via calendar triggers — no extra work needed.

On **Android**, the plugin uses exact alarms (`setExactAndAllowWhileIdle`) with **automatic self-rescheduling**. When a repeating notification fires, the native receiver immediately schedules the next occurrence. This means:

- Notifications fire at **exact times**, not batched by the OS
- Works reliably under **Doze mode** and **battery optimization**
- Survives **device reboots** (the BootReceiver restores all scheduled notifications)
- No app-level rescheduling needed — the plugin handles everything natively

**Example: Daily habit reminder**

```php
// Schedule once — fires every day at the specified time automatically
LocalNotifications::schedule([
    'id' => 'habit-meditation',
    'title' => 'Time to Meditate',
    'body' => 'Your 10-minute session is waiting',
    'at' => now()->setTime(7, 0)->timestamp,
    'repeat' => RepeatInterval::Daily,
    'sound' => true,
    'actions' => [
        ['id' => 'done', 'title' => 'Done'],
        ['id' => 'snooze', 'title' => 'Snooze'],
    ],
]);

// To stop it, just cancel by id
LocalNotifications::cancel('habit-meditation');
```

> **Note:** On Android, `repeat: 'minute'` intervals may experience slight drift (~9 minutes) due to system limits on `setExactAndAllowWhileIdle` frequency in Doze mode. For `hourly`, `daily`, and `weekly` intervals this is not an issue.
