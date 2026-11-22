# Upgrading

## Unreleased: NativePHP 3.3.11+ / 4.6.1+ required

### Update NativePHP with the plugin

`nativephp/mobile` 3.3.11 and 4.6.1 fix security advisory GHSA-83jv-8h7x-287p. Native events reach PHP through `POST /_native/api/events`, which now constructs only event classes that implement `Native\Mobile\Events\Contracts\DispatchableFromNative`. The plugin's events implement that interface, and it does not exist in earlier NativePHP releases, so the plugin now requires `nativephp/mobile` `^3.3.11|^4.6.1`. Update both in one step and rebuild:

```bash
# NativePHP for Mobile v3
composer require ikromjon/nativephp-mobile-local-notifications "nativephp/mobile:^3.3.11" -W

# NativePHP for Mobile v4
composer require ikromjon/nativephp-mobile-local-notifications "nativephp/mobile:^4.6.1" -W

php artisan native:install --force
```

`-W` lets Composer update NativePHP's own dependencies along with it. The rebuild is for NativePHP's native changes: the plugin's Swift and Kotlin code did not change. From 3.3.12 and 4.8.0 the rebuilt app also signs its native events.

### What changes

- **Laravel listeners receive the events again.** On NativePHP 3.3.11+ and 4.6.1+, `Event::listen` listeners and event subscribers stopped receiving every event of this plugin, because none implemented the marker. They now do. Livewire `#[OnNative]`, JavaScript `On(...)` and NativePHP 4 `#[On]` handlers were never affected and need no change.
- **`NotificationActionPressed` has `snoozed` and `snoozeSeconds`.** They are set when a native snooze button is pressed (`false` and `null` otherwise). On NativePHP v3 a snooze press used to make the event fail to construct, so listeners never saw it. A Livewire handler can receive the two values by adding `bool $snoozed = false, ?int $snoozeSeconds = null` parameters.

## From v1.10.x to v1.11.0

### Rebuild required

v1.11.0 changes native (Swift/Kotlin) code to add notification priority and silent delivery. Rebuild your app after updating:

```bash
php artisan native:install --force
```

### Priority & silent (new features, no breaking changes)

- Notifications scheduled without `priority`/`silent` behave exactly as before — high-importance delivery with heads-up on Android. Note that `priority: 'default'` is *lower* than this legacy behavior (no heads-up); only set it deliberately.
- **iOS:** `priority: 'high'` and `'urgent'` need the Time Sensitive Notifications capability in your app's entitlements; `urgent` uses critical alerts only with Apple's critical-alerts entitlement and otherwise downgrades to time-sensitive automatically. See [permissions](permissions.md).
- **Android:** once you use priorities, users will see additional notification channels (e.g. "Notifications (high)") in system settings — channel importance is immutable on Android, so each priority level requires its own channel.

## From v1.7.x to v1.8.0

### Native Snooze

Snooze is a new feature — no breaking changes. Action buttons now support an optional `snooze` parameter (seconds). If you're already using actions, they continue to work as before.

### Snooze field in Android serialization

v1.8.0 fixes a bug where the `snooze` field was stripped during Android action serialization. If you were working around this by re-adding the field manually, you can remove that workaround.

## From v1.6.x to v1.7.0

### Laravel Notification Channel

New feature — no breaking changes. You can optionally use `LocalNotificationChannel` and `LocalNotificationMessage` alongside the existing Facade and DTO approaches.

### Android Action Buttons Fix

v1.7.0 fixes a type coercion bug that prevented action buttons from appearing on Android. If you had a workaround for this, it can be removed.

## From v1.5.x to v1.6.0

### Notification Updates

v1.6.0 adds the `update()` method and `NotificationUpdated` event. You can now update a pending notification's content or timing without canceling and rescheduling manually. This is a new feature with no breaking changes.

## From v1.3.x to v1.4.0

### Cold-Start Init Component

The `<x-local-notifications::init />` Blade component was introduced to handle cold-start tap events reliably. If you previously had manual bridge-call timing logic for cold-start flushes, replace it with the component. See [Getting Started — Cold-Start Tap Events](getting-started.md#cold-start-tap-events).

### Publishable Config

v1.4.0 adds a publishable config file. Run `php artisan vendor:publish --tag=local-notifications-config` to customize defaults.

### Livewire Event Handling

If you were using positional parameters in your `#[OnNative]` handlers, switch to named parameters:

```php
// Before (may not work correctly)
public function onTapped($data): void

// After
public function onTapped(string $id = '', string $title = '', string $body = '', array $data = []): void
```

## General Upgrade Steps

1. Update the package:
   ```bash
   composer update ikromjon/nativephp-mobile-local-notifications
   ```

2. Rebuild your native app (plugin changes require a native build):
   ```bash
   php artisan native:run android
   # or
   php artisan native:run ios
   ```

3. If you published the config file, check for new options:
   ```bash
   php artisan vendor:publish --tag=local-notifications-config --force
   ```
   Review the diff to avoid overwriting your customizations.
