# NativePHP Mobile Local Notifications

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)
[![Total Downloads](https://img.shields.io/packagist/dt/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)
[![License](https://img.shields.io/packagist/l/ikromjon/nativephp-mobile-local-notifications.svg)](https://packagist.org/packages/ikromjon/nativephp-mobile-local-notifications)

Schedule, manage, and cancel local notifications in your NativePHP Mobile app — no server or Firebase required.

## How is this different?

| Plugin | What it does | Requires |
|--------|-------------|----------|
| **nativephp/mobile-dialog** | Toast/snackbar messages (in-app only, disappear when app closes) | Nothing |
| **nativephp/mobile-firebase** | Push notifications from a server via FCM/APNs | Firebase project, server, internet |
| **This plugin** | Local notifications scheduled on-device | Nothing — works offline |

## Features

- Schedule notifications with a delay or at a specific time
- Repeat intervals: minute, hourly, daily, weekly
- Custom sounds, badges, and data payloads
- Cancel individual or all notifications
- List pending notifications
- Permission management (Android 13+, iOS)
- Survives device reboot (Android)
- Events for notification lifecycle (scheduled, received, tapped)
- Works completely offline — no server or Firebase needed

## Installation

```bash
composer require ikromjon/nativephp-mobile-local-notifications
```

Register the plugin in your `app/Providers/NativeServiceProvider.php`:

```php
use Native\Mobile\Facades\System;

public function boot(): void
{
    System::enablePlugins([
        \Ikromjon\LocalNotifications\LocalNotificationsServiceProvider::class,
    ]);
}
```

Build your app (plugin requires a native build — it does not work with Jump):

```bash
php artisan native:run android
# or
php artisan native:run ios
```

## Usage

### Request Permission

Required on Android 13+ and iOS before notifications can be shown.

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

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

// Repeating notification
LocalNotifications::schedule([
    'id' => 'daily-checkin',
    'title' => 'Daily Check-in',
    'body' => 'How are you feeling today?',
    'delay' => 60,
    'repeat' => 'daily',
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
```

### Schedule Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique identifier for the notification |
| `title` | string | Yes | Notification title |
| `body` | string | Yes | Notification body text |
| `delay` | int | No | Delay in seconds from now |
| `at` | int | No | Unix timestamp to fire at |
| `repeat` | string | No | Repeat interval (see table below) |
| `sound` | bool | No | Play sound (default: `true`) |
| `badge` | int | No | Badge number on app icon (iOS) |
| `data` | array | No | Custom data payload (available in tapped event) |
| `subtitle` | string | No | Subtitle text (iOS: subtitle, Android: subtext) |
| `image` | string | No | Image URL to display in the notification |
| `bigText` | string | No | Expanded body text shown when notification is expanded |

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

## Listening to Events

Use the `#[OnNative]` attribute in your Livewire components:

```php
use Native\Mobile\Attributes\OnNative;
use Ikromjon\LocalNotifications\Events\NotificationScheduled;
use Ikromjon\LocalNotifications\Events\NotificationReceived;
use Ikromjon\LocalNotifications\Events\NotificationTapped;
use Ikromjon\LocalNotifications\Events\PermissionGranted;
use Ikromjon\LocalNotifications\Events\PermissionDenied;

#[OnNative(NotificationScheduled::class)]
public function onScheduled($data)
{
    // Notification was scheduled: $data['id'], $data['title'], $data['body']
}

#[OnNative(NotificationReceived::class)]
public function onReceived($data)
{
    // Notification was delivered to the device
}

#[OnNative(NotificationTapped::class)]
public function onTapped($data)
{
    // User tapped a notification: $data['id'], $data['data']
}

#[OnNative(PermissionGranted::class)]
public function onPermissionGranted()
{
    // Permission was granted
}

#[OnNative(PermissionDenied::class)]
public function onPermissionDenied()
{
    // Permission was denied
}
```

## Repeat Intervals

| Value | Description |
|-------|-------------|
| `minute` | Every minute |
| `hourly` | Every hour |
| `daily` | Every day |
| `weekly` | Every week |

## Requirements

- PHP 8.2+
- NativePHP Mobile v3+
- iOS 18.2+ / Android API 33+

## License

MIT
