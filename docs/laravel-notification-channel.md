# Laravel Notification Channel

Use the standard Laravel Notification pattern instead of the Facade.

## Creating a Notification

```php
use Illuminate\Notifications\Notification;
use Ikromjon\LocalNotifications\Notifications\LocalNotificationChannel;
use Ikromjon\LocalNotifications\Notifications\LocalNotificationMessage;
use Ikromjon\LocalNotifications\Notifications\HasLocalNotification;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

class DailyReminderNotification extends Notification implements HasLocalNotification
{
    public function via($notifiable): array
    {
        return [LocalNotificationChannel::class];
    }

    public function toLocalNotification($notifiable): LocalNotificationMessage
    {
        return LocalNotificationMessage::create()
            ->id('reminder-' . $notifiable->id)
            ->title('Daily Reminder')
            ->body('Time to check in!')
            ->repeat(RepeatInterval::Daily)
            ->sound()
            ->action('done', 'Done')
            ->action('skip', 'Skip', destructive: true)
            ->action('snooze', 'Snooze (5m)', snooze: 300);
    }
}

// Send it
$user->notify(new DailyReminderNotification());
```

## Fluent Builder Methods

`LocalNotificationMessage` provides a fluent API that mirrors all [schedule parameters](scheduling.md#schedule-parameters):

```php
LocalNotificationMessage::create()
    ->id('example')
    ->title('Title')
    ->body('Body text')
    ->subtitle('Subtitle')
    ->image('https://example.com/image.jpg')
    ->bigText('Expanded text...')
    ->delay(60)
    ->at(now()->addHour()->timestamp)
    ->repeat(RepeatInterval::Daily)
    ->repeatIntervalSeconds(7200)
    ->repeatDays([1, 2, 3, 4, 5])
    ->repeatCount(3)
    ->sound()                           // default system sound (pick one)
    // ->sound('alert.wav')             // OR: custom sound file
    // ->soundName('chime.caf')         // OR: dedicated custom sound method
    ->badge(1)
    ->data(['key' => 'value'])
    ->action('done', 'Done')
    ->action('snooze', 'Snooze', snooze: 300);
```

## When to Use

The Facade and DTO approaches continue to work as before — the Notification channel is an additional option for teams that prefer Laravel's built-in notification system.

| Approach | Best for |
|----------|----------|
| **Facade** (`LocalNotifications::schedule(...)`) | Quick scheduling, simple use cases |
| **DTO** (`NotificationOptions`) | Type safety, IDE autocompletion |
| **Notification Channel** | Teams using Laravel's notification system, multi-channel notifications |
