<p align="center">
  <img src="logo.png" width="120" alt="Local Notifications Logo">
</p>

# NativePHP Mobile Local Notifications

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)
[![Tests](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/actions/workflows/tests.yml/badge.svg)](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)
[![License](https://img.shields.io/packagist/l/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)

Schedule, manage, and cancel local notifications in your NativePHP Mobile app — no server or Firebase required.

## Quick Start

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

// Request permission (required on Android 13+ and iOS)
LocalNotifications::requestPermission();

// Schedule a notification in 10 seconds
LocalNotifications::schedule([
    'id' => 'welcome',
    'title' => 'Hello!',
    'body' => 'Your first local notification',
    'delay' => 10,
]);
```

## How is this different?

| Plugin | What it does | Requires |
|--------|-------------|----------|
| **nativephp/mobile-dialog** | Toast/snackbar messages (in-app only, disappear when app closes) | Nothing |
| **nativephp/mobile-firebase** | Push notifications from a server via FCM/APNs | Firebase project, server, internet |
| **This plugin** | Local notifications scheduled on-device | Nothing — works offline |

## What's New in v1.7.0

- **Laravel Notification channel** — Use the standard `$user->notify()` pattern with `LocalNotificationChannel` and fluent `LocalNotificationMessage` builder
- **Android action buttons fix** — Fixed a type coercion bug that prevented action buttons from appearing on Android devices

See the full [CHANGELOG](CHANGELOG.md) for details.

## Features

- Schedule notifications with a delay or at a specific time
- Repeat intervals: minute, hourly, daily, weekly, monthly, yearly
- Custom repeat intervals (any duration >= 60 seconds)
- Day-of-week scheduling (e.g. every Mon/Wed/Fri at 9 AM)
- Repeat count limits (fire N times then stop)
- Rich content: images, subtitles, and expanded text
- Action buttons with text input support (configurable limit, default 3)
- Custom sounds, badges, and data payloads
- Cancel individual or all notifications
- List pending notifications
- Permission management (Android 13+, iOS)
- Survives device reboot (Android)
- Events for notification lifecycle (scheduled, received, tapped, action pressed)
- Cold-start tap event auto-flush via Blade component
- Works completely offline — no server or Firebase needed

## Installation

```bash
composer require ikromjon/nativephp-mobile-local-notifications

php artisan native:plugin:register ikromjon/nativephp-mobile-local-notifications
```

> **Note:** If you don't have a `NativeServiceProvider` yet, publish it first:
> ```bash
> php artisan vendor:publish --tag=nativephp-plugins-provider
> ```

Build your app (plugin requires a native build — it does not work with Jump):

```bash
php artisan native:run android
# or
php artisan native:run ios
```

## Configuration

Optionally publish the config file to customize defaults:

```bash
php artisan vendor:publish --tag=local-notifications-config
```

This creates `config/local-notifications.php` where you can set:

| Key | Default | Platform | Description |
|-----|---------|----------|-------------|
| `channel_id` | `nativephp_local_notifications` | Android | Notification channel ID. Change if you use multiple notification plugins |
| `channel_name` | `Local Notifications` | Android | Channel name shown in device notification settings |
| `channel_description` | `Notifications scheduled by the app` | Android | Channel description in device settings |
| `max_actions` | `3` | Both | Max action buttons per notification |
| `min_repeat_interval_seconds` | `60` | Both | Minimum custom repeat interval in seconds |
| `default_sound` | `true` | Both | Play sound when no explicit `sound` parameter is provided |
| `tap_detection_delay_ms` | `500` | Android | Warm-start tap detection delay. Most apps should not change this |
| `navigation_replay_duration_ms` | `15000` | Android | Cold-start event replay window. Most apps should not change this |

You can also use environment variables for channel settings:

```env
LOCAL_NOTIFICATIONS_CHANNEL_ID=my_app_notifications
LOCAL_NOTIFICATIONS_CHANNEL_NAME="My App Alerts"
```

## Cold-Start Tap Events

When a user taps a notification while the app is closed (cold start), the `NotificationTapped` event is queued on the native side but only delivered when a bridge function is called. The init component automates this for all frontend stacks.

### 1. Add the init component before `</body>`

**Livewire apps** — place after `@livewireScripts`:

```blade
{{-- resources/views/layouts/app.blade.php --}}
    @livewireScripts
    <x-local-notifications::init />
</body>
```

**Inertia / Vue / React / plain JS** — place before `</body>`:

```blade
{{-- resources/views/app.blade.php --}}
    @inertiaHead
    @vite(['resources/js/app.js'])
    <x-local-notifications::init />
</body>
```

The component auto-detects the frontend stack. For Livewire, it flushes after `livewire:navigated`. For other stacks, it flushes after `window.load` with a delay to let components mount and register event listeners.

### 2. Put the listener on your landing page component

```php
// The component at route "/" — the page that opens on cold start
#[OnNative(NotificationTapped::class)]
public function onTapped(string $id = '', string $title = '', string $body = '', array $data = []): void
{
    // Handle the tap
}
```

**Why the landing page?** On cold start, the app always opens to `/`. Only components mounted on that page can receive the event. If your listener is on `/settings`, it won't be mounted when the event fires.

### 3. Use named parameters (Livewire 3 & 4)

```php
// Wrong — $data only receives the "data" key from the payload, not the whole event
public function onTapped(array $data = []): void

// Correct — parameter names match the payload keys: id, title, body, data
public function onTapped(string $id = '', string $title = '', string $body = '', array $data = []): void
```

**Why named parameters?** Both Livewire 3 and 4 dispatch event payloads as named arguments (`{id, title, body, data}`). Each parameter name maps to a key in the payload. A parameter named `$data` only receives the `data` key (the custom data field), not the entire event payload.

### 4. Do NOT call bridge functions in mount() on the landing page

```php
// WRONG — steals the cold-start event before the WebView is ready
public function mount(): void
{
    LocalNotifications::checkPermission(); // DO NOT do this on the landing page
}
```

**What happens:** `mount()` runs on the server → calls `checkPermission()` → native side finds the queued tap, dispatches the event via JavaScript, and clears the intent → but the WebView is still loading, so the JavaScript gets wiped when the HTML arrives → the init component fires later and calls `checkPermission()` again, but the intent is already consumed → the event is lost.

**Fix:** Remove any bridge calls (`checkPermission()`, `schedule()`, etc.) from `mount()` on your landing page component. The init component handles the timing correctly. You can safely call bridge functions in `mount()` on other pages since cold start always opens `/`.

> **Without the init component**, you would need to manually call any bridge function (e.g. `checkPermission()` from JS or `LocalNotifications::checkPermission()` from PHP) after your components mount to trigger the flush — but be careful about the timing issue described above.

## Usage (PHP)

### Request Permission

Required on Android 13+ and iOS before notifications can be shown.

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

$result = LocalNotifications::requestPermission();
// Returns: ['granted' => true] or ['granted' => false, 'status' => 'pending']
```

### Schedule a Notification

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

// With rich content (image, subtitle, expanded text)
LocalNotifications::schedule([
    'id' => 'promo',
    'title' => 'New Arrival',
    'body' => 'Check out our latest product',
    'subtitle' => 'Limited time offer',
    'image' => 'https://example.com/product.jpg',
    'bigText' => 'We just launched an amazing new product that you will love. Tap to learn more and get 20% off your first order!',
    'delay' => 60,
]);

// With action buttons
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

// With native snooze (reschedules without opening the app)
LocalNotifications::schedule([
    'id' => 'alarm-1',
    'title' => 'Wake Up!',
    'body' => 'Time to start your day',
    'delay' => 10,
    'actions' => [
        ['id' => 'dismiss', 'title' => 'Dismiss'],
        ['id' => 'snooze', 'title' => 'Snooze (5m)', 'snooze' => 300],
        ['id' => 'snooze10', 'title' => 'Snooze (10m)', 'snooze' => 600],
    ],
]);
```

### Schedule Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique identifier for the notification |
| `title` | string | Yes | Notification title |
| `body` | string | Yes | Notification body text |
| `delay` | int | No | Delay in seconds from now |
| `at` | int | No | Unix timestamp to fire at |
| `repeat` | RepeatInterval\|string | No | Repeat interval (see table below) |
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

### Cancel Notifications

```php
// Cancel a specific notification
LocalNotifications::cancel('reminder-1');

// Cancel all notifications
LocalNotifications::cancelAll();
```

### List Pending Notifications

```php
$result = LocalNotifications::getPending();
// Returns: ['success' => true, 'notifications' => '[...]', 'count' => 3]
```

### Check Permission Status

```php
$result = LocalNotifications::checkPermission();
// Returns: ['status' => 'granted'] or ['status' => 'denied']
```

### Update an Existing Notification

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

## Listening to Events (Livewire)

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
public function onActionPressed(string $notificationId = '', string $actionId = '', ?string $inputText = null, ?array $data = null): void
{
    // Action button pressed
    // $inputText is set when the action has 'input' => true
}
```

### Event Payload Keys

| Event | Payload keys |
|-------|-------------|
| `NotificationScheduled` | `id`, `title`, `body` |
| `NotificationReceived` | `id`, `title`, `body`, `data` |
| `NotificationTapped` | `id`, `title`, `body`, `data` |
| `NotificationUpdated` | `id`, `title`, `body` |
| `NotificationActionPressed` | `notificationId`, `actionId`, `inputText`, `data` |
| `PermissionGranted` | _(none)_ |
| `PermissionDenied` | _(none)_ |

## Listening to Events (Laravel)

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

## Usage (JavaScript)

For apps using Inertia with Vue or React, import functions directly from the plugin's JavaScript library. The CSRF token is read automatically from your page's `<meta name="csrf-token">` tag.

```js
import {
    schedule,
    cancel,
    cancelAll,
    getPending,
    requestPermission,
    checkPermission,
    update,
    Events,
} from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';

// Request permission
const { granted } = await requestPermission();

// Schedule a notification
await schedule({
    id: 'reminder-1',
    title: 'Reminder',
    body: 'Time to take a break!',
    delay: 300,
});

// Schedule a repeating notification with actions
await schedule({
    id: 'daily-checkin',
    title: 'Daily Check-in',
    body: 'How are you feeling today?',
    at: Math.floor(Date.now() / 1000) + 60, // 1 minute from now
    repeat: 'daily',
    actions: [
        { id: 'done', title: 'Done' },
        { id: 'snooze', title: 'Snooze' },
    ],
});

// Cancel a notification
await cancel('reminder-1');

// Cancel all notifications
await cancelAll();

// List pending notifications
const { notifications, count } = await getPending();

// Check permission status
const { status } = await checkPermission();

// Update an existing notification
await update('reminder-1', { title: 'Updated!', body: 'New body text' });
```

### Listening to Events (JavaScript)

Use the NativePHP `On()` function with the plugin's `Events` constants:

```js
import { On } from '#nativephp';
import { Events } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';

// User tapped a notification
On(Events.NotificationTapped, (payload) => {
    console.log('Tapped:', payload.id, payload.data);
});

// Notification was delivered
On(Events.NotificationReceived, (payload) => {
    console.log('Received:', payload.id);
});

// Action button pressed
On(Events.NotificationActionPressed, (payload) => {
    console.log('Action:', payload.actionId, payload.inputText);
});

// Permission result
On(Events.PermissionGranted, () => console.log('Permission granted'));
On(Events.PermissionDenied, () => console.log('Permission denied'));
```

### Available JavaScript Functions

| Function | Parameters | Returns |
|----------|-----------|---------|
| `schedule(options)` | Object with `id`, `title`, `body`, and optional scheduling params | `{ success, id?, error? }` |
| `cancel(id)` | Notification ID string | `{ success, id?, error? }` |
| `cancelAll()` | None | `{ success, error? }` |
| `getPending()` | None | `{ success, notifications?, count?, error? }` |
| `requestPermission()` | None | `{ granted, status?, error? }` |
| `checkPermission()` | None | `{ status, error? }` |

### Available Event Constants

| Constant | PHP Event Class |
|----------|----------------|
| `Events.NotificationScheduled` | `NotificationScheduled` |
| `Events.NotificationReceived` | `NotificationReceived` |
| `Events.NotificationTapped` | `NotificationTapped` |
| `Events.NotificationActionPressed` | `NotificationActionPressed` |
| `Events.PermissionGranted` | `PermissionGranted` |
| `Events.PermissionDenied` | `PermissionDenied` |

## Repeat Intervals

Use the `RepeatInterval` enum (recommended) or pass the string value directly:

| Enum | String | Description |
|------|--------|-------------|
| `RepeatInterval::Minute` | `'minute'` | Every minute |
| `RepeatInterval::Hourly` | `'hourly'` | Every hour |
| `RepeatInterval::Daily` | `'daily'` | Every day |
| `RepeatInterval::Weekly` | `'weekly'` | Every week |
| `RepeatInterval::Monthly` | `'monthly'` | Every month (handles variable month lengths) |
| `RepeatInterval::Yearly` | `'yearly'` | Every year (handles leap years) |

### Custom Repeat Interval

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

### Day-of-Week Scheduling

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

### Repeat Count Limit

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

### Type-Safe DTO

You can use the `NotificationOptions` DTO instead of arrays for type safety:

```php
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Data\NotificationAction;

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

### Laravel Notification Channel

Use the standard Laravel Notification pattern instead of the Facade:

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

The Facade and DTO approaches continue to work as before — the Notification channel is an additional option for teams that prefer Laravel's built-in notification system.

### Native Snooze

Action buttons can include a `snooze` parameter (in seconds) that reschedules the notification natively — **the app does not need to be open**. When the user presses a snooze action, the notification is dismissed, rescheduled via AlarmManager (Android) or UNTimeIntervalNotificationTrigger (iOS), and reappears after the specified delay.

```php
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

### How Repeating Notifications Work

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

## Required Permissions

The plugin declares all required permissions automatically via `nativephp.json`. No manual configuration needed.

**Android:**

| Permission | Purpose |
|-----------|---------|
| `POST_NOTIFICATIONS` | Show notifications (Android 13+, requested at runtime) |
| `SCHEDULE_EXACT_ALARM` | Schedule notifications at exact times |
| `USE_EXACT_ALARM` | Fallback for exact alarm scheduling |
| `RECEIVE_BOOT_COMPLETED` | Restore scheduled notifications after device reboot |
| `VIBRATE` | Vibrate on notification delivery |

**iOS:**

- Notification authorization is requested at runtime via `requestPermission()` (alert, sound, badge)
- Minimum iOS version: 18.0 (NativePHP baseline)

**Environment variables:** None required. The plugin works entirely on-device with no external services.

## Testing

```bash
# Run tests
composer test

# Run static analysis
composer analyse
```

## Requirements

- PHP 8.3+
- NativePHP Mobile v3+
- iOS 18.0+ / Android API 29+ (matches NativePHP Mobile baseline)

## Example App

**[Daily Habits](https://github.com/Ikromjon1998/daily-habits)** is a full, open-source mobile app built with this plugin. It demonstrates:

- Scheduling daily repeating notifications with `RepeatInterval::Daily`
- Action buttons ("Done" / "Skip" / "Snooze") handled via `NotificationActionPressed`
- Laravel Notification channel integration (`DebugLocalNotification` class)
- Permission management on the Settings screen
- Notification cancellation when habits are deleted
- **Notification Debug panel** with 7 test scenarios covering warm/cold start, action buttons, text input, content/timing updates, and the Laravel Notification channel

Use it as a reference, fork it as a starter for your own app, or contribute to it.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, development workflow, and guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes in each release.

## Support

For questions or issues, use [GitHub Issues](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues) or contact: ikromjon98.98@icloud.com

## License

MIT
