# Action Buttons

Add interactive buttons to notifications that users can tap without opening the app.

## Basic Actions

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

LocalNotifications::schedule([
    'id' => 'message-1',
    'title' => 'New Message',
    'body' => 'Hey, are you free tonight?',
    'delay' => 5,
    'actions' => [
        ['id' => 'reply', 'title' => 'Reply', 'input' => true],
        ['id' => 'like', 'title' => 'Like'],
        ['id' => 'delete', 'title' => 'Delete', 'destructive' => true],
    ],
]);
```

### Action Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique identifier for the action |
| `title` | string | Yes | Button label |
| `input` | bool | No | Show a text input field when pressed |
| `destructive` | bool | No | Style as destructive (red on iOS) |
| `snooze` | int | No | Reschedule delay in seconds (see [Native Snooze](#native-snooze)) |

The maximum number of actions per notification is set by `config('local-notifications.max_actions')` (default 3).

## Native Snooze

Action buttons can include a `snooze` parameter (in seconds) that reschedules the notification natively — **the app does not need to be open**. When the user presses a snooze action, the notification is dismissed, rescheduled via AlarmManager (Android) or UNTimeIntervalNotificationTrigger (iOS), and reappears after the specified delay.

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\Notifications\LocalNotificationMessage;

// Facade
LocalNotifications::schedule([
    'id' => 'alarm',
    'title' => 'Wake Up!',
    'body' => 'Time to start your day',
    'delay' => 10,
    'actions' => [
        ['id' => 'dismiss', 'title' => 'Dismiss'],
        ['id' => 'snooze5', 'title' => 'Snooze 5m', 'snooze' => 300],
        ['id' => 'snooze10', 'title' => 'Snooze 10m', 'snooze' => 600],
    ],
]);

// Fluent builder (Notification channel)
LocalNotificationMessage::create()
    ->id('alarm')
    ->title('Wake Up!')
    ->body('Time to start your day')
    ->delay(10)
    ->action('dismiss', 'Dismiss')
    ->action('snooze5', 'Snooze 5m', snooze: 300)
    ->action('snooze10', 'Snooze 10m', snooze: 600);
```

The `NotificationActionPressed` event payload includes `snoozed: true` and `snoozeSeconds: 300` when a snooze action is pressed, so your app can track snooze usage. The event is stored as a pending event and flushed when the user next opens the app.

## Type-Safe DTO

Use the `NotificationAction` DTO for type safety:

```php
use Ikromjon\LocalNotifications\Data\NotificationAction;

$actions = [
    new NotificationAction(id: 'done', title: 'Done'),
    new NotificationAction(id: 'reply', title: 'Reply', input: true),
    new NotificationAction(id: 'snooze', title: 'Snooze', snooze: 300),
    new NotificationAction(id: 'delete', title: 'Delete', destructive: true),
];
```

## Listening for Action Presses

See the [Events](events.md) documentation for handling `NotificationActionPressed` events in Livewire, Laravel, and JavaScript.
