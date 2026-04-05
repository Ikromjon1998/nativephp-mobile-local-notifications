# Upgrading

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
