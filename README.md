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

## Features

- Schedule notifications with a delay or at a specific time
- Repeat intervals: minute, hourly, daily, weekly, monthly, yearly
- Custom repeat intervals (any duration >= 60 seconds)
- Day-of-week scheduling (e.g. every Mon/Wed/Fri at 9 AM)
- Repeat count limits (fire N times then stop)
- Rich content: images, subtitles, and expanded text
- Action buttons with text input support (configurable limit, default 3)
- Native snooze (reschedules without opening the app)
- Custom sounds, badges, and data payloads
- Cancel individual or all notifications
- List pending notifications
- Update existing notifications
- Permission management (Android 13+, iOS)
- Laravel Notification channel support
- Survives device reboot (Android)
- Events for notification lifecycle (scheduled, received, tapped, action pressed)
- Cold-start tap event auto-flush via Blade component
- Works completely offline — no server or Firebase needed

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | Installation, configuration, cold-start setup, requirements |
| [Scheduling](docs/scheduling.md) | Schedule, cancel, update, list notifications, type-safe DTO |
| [Events](docs/events.md) | Listen in Livewire, Laravel event listeners, or JavaScript |
| [Repeat Intervals](docs/repeat-intervals.md) | Standard intervals, custom durations, day-of-week, count limits |
| [Rich Content](docs/rich-content.md) | Images, subtitles, expanded text |
| [Custom Sounds](docs/custom-sounds.md) | Custom sound files per notification |
| [Action Buttons](docs/action-buttons.md) | Tap actions, text input, native snooze |
| [Laravel Notification Channel](docs/laravel-notification-channel.md) | Standard `$user->notify()` pattern |
| [JavaScript API](docs/javascript-api.md) | Full API for Vue, React, and Inertia apps |
| [Permissions](docs/permissions.md) | Android and iOS permission requirements |
| [Troubleshooting](docs/troubleshooting.md) | Common issues and solutions |
| [Upgrading](docs/upgrading.md) | Migration guides between versions |

## Example App

**[Daily Habits](https://github.com/Ikromjon1998/daily-habits)** is a full, open-source mobile app built with this plugin. It demonstrates scheduling, action buttons, snooze, the Laravel Notification channel, and a notification debug panel with 7 test scenarios.

## Testing

```bash
composer test        # Run tests
composer analyse     # Run static analysis
```

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, development workflow, and guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes in each release.

## Roadmap

See [ROADMAP.md](ROADMAP.md) for planned features and their status.

## Support

For questions or issues, use [GitHub Issues](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues) or contact: ikromjon98.98@icloud.com

## License

MIT
