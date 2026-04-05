# Events

The plugin dispatches events for the full notification lifecycle. You can listen to them in Livewire, standard Laravel listeners, or JavaScript.

## Event Payload Keys

| Event | Payload keys |
|-------|-------------|
| `NotificationScheduled` | `id`, `title`, `body` |
| `NotificationReceived` | `id`, `title`, `body`, `data` |
| `NotificationTapped` | `id`, `title`, `body`, `data` |
| `NotificationUpdated` | `id`, `title`, `body` |
| `NotificationActionPressed` | `notificationId`, `actionId`, `data`, `inputText` |
| `PermissionGranted` | _(none)_ |
| `PermissionDenied` | _(none)_ |

## Livewire

Use the `#[OnNative]` attribute in your Livewire components. Parameter names must match the payload keys — Livewire 3 and 4 both map dispatched payload keys to named method parameters.

```php
use Native\Mobile\Attributes\OnNative;
use Ikromjon\LocalNotifications\Events\NotificationScheduled;
use Ikromjon\LocalNotifications\Events\NotificationReceived;
use Ikromjon\LocalNotifications\Events\NotificationTapped;
use Ikromjon\LocalNotifications\Events\NotificationUpdated;
use Ikromjon\LocalNotifications\Events\PermissionGranted;
use Ikromjon\LocalNotifications\Events\PermissionDenied;
use Ikromjon\LocalNotifications\Events\NotificationActionPressed;

#[OnNative(NotificationScheduled::class)]
public function onScheduled(string $id = '', string $title = '', string $body = ''): void
{
    // Notification was scheduled
}

#[OnNative(NotificationUpdated::class)]
public function onUpdated(string $id = '', string $title = '', string $body = ''): void
{
    // Notification was updated
}

#[OnNative(NotificationReceived::class)]
public function onReceived(string $id = '', string $title = '', string $body = '', ?array $data = null): void
{
    // Notification was delivered to the device (app in foreground)
}

#[OnNative(NotificationTapped::class)]
public function onTapped(string $id = '', string $title = '', string $body = '', ?array $data = null): void
{
    // User tapped a notification
}

#[OnNative(PermissionGranted::class)]
public function onPermissionGranted(): void
{
    // Permission was granted
}

#[OnNative(PermissionDenied::class)]
public function onPermissionDenied(): void
{
    // Permission was denied
}

#[OnNative(NotificationActionPressed::class)]
public function onActionPressed(string $notificationId = '', string $actionId = '', ?array $data = null, ?string $inputText = null): void
{
    // Action button pressed
    // $inputText is set when the action has 'input' => true
}
```

## Laravel Event Listeners

For apps using native UI (EDGE components) or any context without Livewire, register standard Laravel event listeners. Events are dispatched to the PHP backend regardless of the frontend stack.

```php
// app/Listeners/HandleNotificationTap.php
namespace App\Listeners;

use Ikromjon\LocalNotifications\Events\NotificationTapped;

class HandleNotificationTap
{
    public function handle(NotificationTapped $event): void
    {
        // $event->id, $event->title, $event->body, $event->data
    }
}
```

Register in your `AppServiceProvider` or `EventServiceProvider`:

```php
use App\Listeners\HandleNotificationTap;
use App\Listeners\HandleNotificationAction;
use Ikromjon\LocalNotifications\Events\NotificationTapped;
use Ikromjon\LocalNotifications\Events\NotificationActionPressed;

protected $listen = [
    NotificationTapped::class => [HandleNotificationTap::class],
    NotificationActionPressed::class => [HandleNotificationAction::class],
];
```

This approach works with **all frontend stacks**: Livewire, Inertia (Vue/React), and native UI (EDGE) — no WebView required.

## JavaScript

See [JavaScript API — Events](javascript-api.md#listening-to-events) for event handling in Vue, React, and Inertia apps.
