# Scheduling Notifications

## Request Permission

Required on Android 13+ and iOS before notifications can be shown.

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

$result = LocalNotifications::requestPermission();
// Returns: ['granted' => true] or ['granted' => false, 'status' => 'pending']
```

## Schedule a Notification

```php
// Fire after a delay (seconds)
LocalNotifications::schedule([
    'id' => 'reminder-1',
    'title' => 'Reminder',
    'body' => 'Time to take a break!',
    'delay' => 300, // 5 minutes from now
]);

// Fire at a specific time
LocalNotifications::schedule([
    'id' => 'meeting-alert',
    'title' => 'Meeting in 15 minutes',
    'body' => 'Team standup in the main room',
    'at' => now()->addMinutes(15)->timestamp,
]);

// Repeating daily notification (using enum — recommended)
LocalNotifications::schedule([
    'id' => 'daily-checkin',
    'title' => 'Daily Check-in',
    'body' => 'How are you feeling today?',
    'at' => now()->setTime(9, 0)->timestamp, // every day at 9:00 AM
    'repeat' => RepeatInterval::Daily,
]);

// Repeating hourly notification
LocalNotifications::schedule([
    'id' => 'hydration',
    'title' => 'Drink Water',
    'body' => 'Stay hydrated!',
    'delay' => 3600, // first one in 1 hour, then every hour
    'repeat' => RepeatInterval::Hourly,
]);

// String values still work for backwards compatibility
LocalNotifications::schedule([
    'id' => 'weekly-review',
    'title' => 'Weekly Review',
    'body' => 'Time to review your progress',
    'at' => now()->next('Monday')->setTime(10, 0)->timestamp,
    'repeat' => 'weekly', // string also accepted
]);

// With custom data and options
LocalNotifications::schedule([
    'id' => 'task-due',
    'title' => 'Task Due',
    'body' => 'Complete the report',
    'delay' => 3600,
    'sound' => true,
    'badge' => 1,
    'data' => ['task_id' => 42, 'priority' => 'high'],
]);
```

## Schedule Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique identifier for the notification |
| `title` | string | Yes | Notification title |
| `body` | string | Yes | Notification body text |
| `delay` | int | No | Delay in seconds from now |
| `at` | int | No | Unix timestamp to fire at |
| `repeat` | RepeatInterval\|string | No | Repeat interval (see [Repeat Intervals](repeat-intervals.md)) |
| `repeatIntervalSeconds` | int | No | Custom repeat interval in seconds (min 60). Mutually exclusive with `repeat` |
| `repeatDays` | array\<int\> | No | Days of week to repeat (1=Mon..7=Sun). Requires `at`. Mutually exclusive with `repeat` |
| `repeatCount` | int | No | Limit how many times the notification repeats (min 1). Requires a repeat mechanism |
| `sound` | bool | No | Play sound (default from `config('local-notifications.default_sound')`, initially `true`) |
| `badge` | int | No | Badge number on app icon (iOS) |
| `data` | array | No | Custom data payload (available in tapped event) |
| `subtitle` | string | No | Subtitle text (iOS: subtitle, Android: subtext) |
| `image` | string | No | Image URL (http/https only) to display in the notification |
| `bigText` | string | No | Expanded body text shown when notification is expanded |
| `actions` | array | No | Action buttons (limit set by `config('local-notifications.max_actions')`, default 3), each with `id`, `title`, optional `destructive`, `input`, and `snooze` (seconds) |

Either `delay` or `at` should be provided. If neither is set, the notification fires after 1 second.

## Cancel Notifications

```php
// Cancel a specific notification
LocalNotifications::cancel('reminder-1');

// Cancel all notifications
LocalNotifications::cancelAll();
```

## List Pending Notifications

```php
$result = LocalNotifications::getPending();
// Returns: ['success' => true, 'notifications' => '[...]', 'count' => 3]
```

## Check Permission Status

```php
$result = LocalNotifications::checkPermission();
// Returns: ['status' => 'granted'] or ['status' => 'denied']
```

## Update an Existing Notification

Update a pending notification's content or timing without canceling and rescheduling manually.

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\Data\NotificationOptions;

// Update only content (preserves original schedule)
LocalNotifications::update('reminder-1', [
    'title' => 'Updated Reminder',
    'body' => 'New reminder text',
]);

// Update timing (reschedules the notification)
LocalNotifications::update('reminder-1', [
    'title' => 'Rescheduled',
    'body' => 'Moved to tomorrow',
    'at' => now()->addDay()->timestamp,
]);

// Update with DTO
LocalNotifications::update('reminder-1', new NotificationOptions(
    id: 'reminder-1',
    title: 'Updated via DTO',
    body: 'Works with DTOs too',
));
```

Returns `['success' => false, 'error' => 'Notification not found: ...']` if the ID doesn't exist.

## Type-Safe DTO

You can use the `NotificationOptions` DTO instead of arrays for type safety:

```php
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Data\NotificationAction;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

$options = new NotificationOptions(
    id: 'dto-example',
    title: 'DTO Notification',
    body: 'Using the type-safe DTO',
    repeat: RepeatInterval::Daily,
    at: now()->setTime(9, 0)->timestamp,
    repeatCount: 7,
    actions: [
        new NotificationAction(id: 'done', title: 'Done'),
        new NotificationAction(id: 'snooze', title: 'Snooze', snooze: 300),
    ],
);

LocalNotifications::schedule($options);
```
