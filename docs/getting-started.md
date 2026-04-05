# Getting Started

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
LOCAL_NOTIFICATIONS_CHANNEL_DESCRIPTION="Custom channel description"
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

## Requirements

- PHP 8.3+
- NativePHP Mobile v3+
- iOS 18.0+ / Android API 29+ (matches NativePHP Mobile baseline)
