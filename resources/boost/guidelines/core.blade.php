<local-notifications-guidelines>

# Local Notifications Plugin — AI Guidelines

## Facade

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
```

### Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `schedule($options)` | `NotificationOptions\|array` | `array` | Schedule a notification with delay, timestamp, repeat, actions, etc. |
| `cancel($id)` | `string` | `array` | Cancel a notification by ID. Also cancels day-of-week sub-alarms. |
| `cancelAll()` | — | `array` | Cancel all scheduled notifications. |
| `getPending()` | — | `array` | List all pending notifications. Day-of-week sub-alarms are aggregated. |
| `requestPermission()` | — | `array` | Request notification permission (Android 13+, iOS). |
| `checkPermission()` | — | `array` | Check current permission status (`granted`, `denied`, `notDetermined`). |

### Schedule Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique notification identifier |
| `title` | string | Yes | Notification title |
| `body` | string | Yes | Notification body text |
| `delay` | int | No | Seconds from now |
| `at` | int | No | Unix timestamp |
| `repeat` | RepeatInterval\|string | No | `minute`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` |
| `repeatIntervalSeconds` | int | No | Custom interval >= 60s. Mutually exclusive with `repeat` |
| `repeatDays` | array\<int\> | No | ISO weekdays (1=Mon..7=Sun). Requires `at`. Mutually exclusive with `repeat` |
| `repeatCount` | int | No | Limit repetitions (min 1) |
| `sound` | bool | No | Default `true` |
| `badge` | int | No | App icon badge (iOS) |
| `data` | array | No | Custom payload passed through to events |
| `subtitle` | string | No | iOS subtitle / Android subtext |
| `image` | string | No | http/https URL for rich notification image |
| `bigText` | string | No | Expanded text on notification pull-down |
| `actions` | array | No | Up to 3 buttons: `[{id, title, destructive?, input?}]` |

### Type-Safe DTOs

```php
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Data\NotificationAction;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

LocalNotifications::schedule(new NotificationOptions(
    id: 'habit-1',
    title: 'Drink Water',
    body: 'Stay hydrated!',
    at: now()->setTime(9, 0)->timestamp,
    repeat: RepeatInterval::Daily,
    actions: [
        new NotificationAction(id: 'done', title: 'Done'),
        new NotificationAction(id: 'snooze', title: 'Snooze'),
    ],
));
```

## Events

| Event | Payload | When |
|-------|---------|------|
| `NotificationScheduled` | `id`, `title`, `body` | Notification successfully scheduled |
| `NotificationReceived` | `id`, `title`, `body`, `data?` | Notification delivered to device |
| `NotificationTapped` | `id`, `title`, `body`, `data?` | User tapped the notification |
| `NotificationActionPressed` | `notificationId`, `actionId`, `data?`, `inputText?` | User pressed an action button |
| `PermissionGranted` | — | Permission granted |
| `PermissionDenied` | — | Permission denied |

Events are dispatched to **all** contexts simultaneously. Listen in whichever fits your stack:

### Livewire

```php
#[OnNative(NotificationTapped::class)]
public function onTapped($data) { /* $data['id'], $data['data'] */ }
```

### Laravel Listeners (works with native UI / EDGE — no WebView needed)

```php
// app/Listeners/HandleNotificationTap.php
use Ikromjon\LocalNotifications\Events\NotificationTapped;

class HandleNotificationTap
{
    public function handle(NotificationTapped $event): void
    {
        // $event->id, $event->title, $event->body, $event->data
    }
}
```

### JavaScript (Inertia / Vue / React)

```js
import { schedule, cancel, Events } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';
import { On } from '#nativephp';

await schedule({ id: 'r1', title: 'Reminder', body: 'Hello', delay: 60 });
await cancel('r1');

On(Events.NotificationTapped, (payload) => {
    console.log('Tapped:', payload.id, payload.data);
});
```

## Configuration (v1.4.0)

Publish with `php artisan vendor:publish --tag=local-notifications-config`.

| Key | Default | Description |
|-----|---------|-------------|
| `channel_id` | `nativephp_local_notifications` | Android notification channel ID |
| `channel_name` | `Local Notifications` | Android notification channel name |
| `channel_description` | `Notifications scheduled by the app` | Android channel description |
| `max_actions` | `3` | Max action buttons per notification |
| `min_repeat_interval_seconds` | `60` | Minimum custom repeat interval |
| `default_sound` | `true` | Play sound when no explicit `sound` parameter |
| `tap_detection_delay_ms` | `500` | Android warm-start tap detection delay |
| `navigation_replay_duration_ms` | `15000` | Android cold-start `livewire:navigated` replay window |

Config is injected into bridge calls via `_config` key — Android reads values at runtime.

## Event Dispatch & Tap Detection

- **Pending event flush:** Every bridge function flushes any queued pending events (e.g. `NotificationTapped` from a cold-start tap). The first bridge call after app launch delivers all queued events.
- **Warm-start tap detection (Android):** An `Application.ActivityLifecycleCallbacks` runs `detectTappedNotifications()` on every `onResume` with configurable delay (`tap_detection_delay_ms`). When the user taps a notification while the app is open, the event fires immediately when the app returns to foreground — no bridge call needed.
- **Cold-start navigation replay (Android):** On cold start, a `livewire:navigated` JS listener replays `NotificationTapped` on every `wire:navigate` navigation for configurable duration (`navigation_replay_duration_ms`). This ensures the event reaches the destination page's `#[OnNative]` handlers even when the first bridge call runs on a different page.
- **SharedPreferences-based tap tracking (Android):** When a notification fires, a tap payload is stored. On user swipe-dismiss, a `deleteIntent` clears it. On user tap (auto-cancel), the payload persists. The plugin compares stored payloads against `NotificationManager.getActiveNotifications()` to detect taps.
- **Android `livewire:init` fallback:** On cold start, Livewire may not be loaded when native events are dispatched. The plugin injects a `livewire:init` JS listener as a fallback — events are replayed when Livewire initializes. This is automatic and requires no user action.
- **iOS limitation:** The `livewire:init` and `livewire:navigated` fallbacks are Android-only. On iOS, the plugin relies on the NativePHP core's WebView user script for Livewire dispatch. If Livewire timing is an issue on iOS cold start, ensure a bridge call (e.g. `checkPermission()`) happens after the page loads.

## Common Patterns

- Always call `requestPermission()` before scheduling (Android 13+, iOS).
- Use `cancel(id)` before `schedule()` when updating an existing notification.
- `repeatDays` creates one sub-alarm per day — `cancel()` and `getPending()` handle aggregation automatically.
- Notification IDs should be deterministic (e.g. `habit-{id}`) so you can cancel without tracking state.
- `data` payload is passed through to `NotificationTapped` and `NotificationActionPressed` events.
- To ensure `NotificationTapped` events are delivered on cold start, make sure your app calls at least one bridge function (e.g. `checkPermission()`) early in the page lifecycle.
- Action buttons: Visible when the user expands (swipe down) the notification. Max 3 buttons per notification.

## Required Permissions

Declared automatically via `nativephp.json` — no manual setup needed.

**Android:** `POST_NOTIFICATIONS`, `SCHEDULE_EXACT_ALARM`, `USE_EXACT_ALARM`, `RECEIVE_BOOT_COMPLETED`, `VIBRATE`
**iOS:** Runtime authorization for alert, sound, badge. Min version: 18.2.

No environment variables or API keys required.

</local-notifications-guidelines>
