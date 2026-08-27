# Localization & Payload Transformers

Notification text is rendered by the **native OS** (Android's `NotificationManager`, iOS's `UNUserNotificationCenter`) — not in a web page. A scheduled notification also fires *later*, often when the app is closed, so there is no page and no JavaScript running at delivery time. Because of this, notification content must be finalized **server-side, in PHP, at schedule time**. Client-side (browser) translation cannot reach it.

There are two ways to do that, from simplest to most powerful.

## 1. Localize at the call site (recommended)

`schedule()` and `update()` accept plain strings, so use Laravel's own translations directly. Nothing in the plugin needs to change:

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

LocalNotifications::schedule([
    'id'    => 'welcome',
    'title' => __('notifications.welcome.title'),
    'body'  => __('notifications.welcome.body'),
    'delay' => 10,
]);
```

The translation is resolved against the app's current locale (`app()->getLocale()`) at the moment you schedule. This is the right approach for the vast majority of apps.

> **Scheduling for a future locale?** The string is translated *now*, not when the notification fires. If a user may change language between scheduling and delivery, store the translation key in `data` and re-resolve on the `NotificationReceived`/`NotificationTapped` event, or use a transformer (below) combined with `App::setLocale()` for the target user.

## 2. Localize everywhere with a transformer

If you send notifications from many places, repeating `__()` at each call site is easy to forget. Register a **payload transformer** once — typically in a service provider's `boot()` — and it runs for every `schedule()` and `update()` call:

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

public function boot(): void
{
    LocalNotifications::transformUsing(function (array $payload): array {
        foreach (['title', 'body', 'subtitle', 'bigText'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = __($payload[$field]);
            }
        }

        // Action button titles are nested.
        if (isset($payload['actions'])) {
            $payload['actions'] = array_map(function (array $action): array {
                $action['title'] = __($action['title']);

                return $action;
            }, $payload['actions']);
        }

        return $payload;
    });
}
```

Now every call site can pass translation keys and stay locale-agnostic:

```php
LocalNotifications::schedule([
    'id'    => 'welcome',
    'title' => 'notifications.welcome.title',   // key, translated by the transformer
    'body'  => 'notifications.welcome.body',
    'actions' => [
        ['id' => 'open', 'title' => 'notifications.actions.open'],
    ],
]);
```

The same hook works with any translation backend — a translation API, DeepL, a glossary lookup, a database of strings — because your callback is ordinary PHP.

## How transformers behave

| Behavior | Detail |
|----------|--------|
| **When it runs** | Immediately before the payload is dispatched to the native layer, **after** validation. |
| **What it receives** | The developer-facing payload array (`id`, `title`, `body`, `actions`, …). It never sees the internal `_config` block. |
| **Which calls** | Only the PHP `schedule()` and `update()` — the content-bearing calls. `cancel()`, `getPending()`, permission calls are untouched, and so is the JavaScript API (below). |
| **Multiple transformers** | Applied in registration order; each receives the previous one's output (a pipeline). |
| **Return value** | Must return the payload array to send. |
| **Validation** | The payload is validated at the call site *and again* after the transformers run. |

Transformers are intended for **content**. Their output is re-validated before dispatch, so a transformer cannot produce a payload the call site would have been rejected for: a reserved `id` suffix, more action buttons than `max_actions` allows, an invalid `priority`, and so on throw `InvalidArgumentException` out of the `schedule()` or `update()` call rather than reaching the device. Structural fields such as `at`, `repeat`, and `repeatDays` are still better changed at the call site, where the intent is visible next to the schedule.

## Transformers do not reach the JavaScript API

Transformers are a PHP-side hook, and the [JavaScript API](javascript-api.md) does not pass through PHP. Its `schedule()` posts to NativePHP's `/_native/api/call` endpoint, which hands the parameters straight to the native bridge — this package's PHP class is never involved, so no transformer runs (and no PHP-side validation applies either).

If you schedule from Vue, React, or Inertia and want your transformers applied, post to a route of your own and schedule from the controller:

```php
// routes/web.php
Route::post('/notifications', function (Request $request) {
    return LocalNotifications::schedule([
        'id'    => $request->string('id'),
        'title' => 'notifications.welcome.title',   // transformers run here
        'body'  => 'notifications.welcome.body',
        'delay' => 10,
    ]);
});
```

Otherwise, translate in JavaScript before calling `schedule()` — the strings are sent as-is.

## Not just for translation

Because a transformer receives the whole payload, it's a general seam for any cross-cutting rule, for example:

```php
LocalNotifications::transformUsing(function (array $payload): array {
    // Force a house-style prefix on every title. Guard the field —
    // update() payloads carry only what you are changing, so `title`
    // may be absent.
    if (isset($payload['title'])) {
        $payload['title'] = '🔔 '.$payload['title'];
    }

    // Guarantee an analytics tag on every notification.
    $payload['data'] = array_merge($payload['data'] ?? [], ['source' => 'app']);

    return $payload;
});
```

## Removing transformers

Call `flushTransformers()` to clear all registered transformers (useful in tests or when reconfiguring at runtime):

```php
LocalNotifications::flushTransformers();
```
