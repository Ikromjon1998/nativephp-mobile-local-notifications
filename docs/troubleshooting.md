# Troubleshooting

## Cold-Start Tap Events Not Firing

**Symptom:** Tapping a notification while the app is closed does nothing — the `NotificationTapped` event never arrives.

**Causes and fixes:**

1. **Missing init component** — Add `<x-local-notifications::init />` before `</body>` in your layout. See [Getting Started — Cold-Start Tap Events](getting-started.md#cold-start-tap-events).

2. **Listener on wrong page** — Cold start always opens `/`. If your `#[OnNative(NotificationTapped::class)]` handler is on `/settings`, it won't be mounted. Move it to your landing page component.

3. **Bridge call in `mount()` stealing the event** — Calling `LocalNotifications::checkPermission()` or any bridge function in `mount()` on your landing page triggers the native event flush before the WebView is ready. The event is dispatched, the HTML overwrites it, and it's lost. Remove bridge calls from `mount()` on the landing page.

4. **Named parameter mismatch** — Livewire maps payload keys to parameter names. `public function onTapped(array $data)` only gets the `data` key, not the full payload. Use `public function onTapped(string $id = '', string $title = '', string $body = '', array $data = [])`.

## Notifications Not Showing on Android

**Symptom:** `schedule()` succeeds but no notification appears.

**Check:**
- Permission granted? Call `requestPermission()` first on Android 13+.
- Notification channel not blocked? Users can disable channels in system settings. Check `Settings > Apps > [Your App] > Notifications`.
- Battery optimization? Some manufacturers (Xiaomi, Huawei, Samsung) aggressively kill background processes. Users may need to whitelist the app.

## Notifications Not Repeating

**Symptom:** First notification fires but subsequent ones don't.

**Check:**
- Are you using `repeat` with `delay` or `at`? Both work, but `at` is more reliable for daily schedules since it anchors to a specific time.
- On Android, `repeat: 'minute'` may drift ~9 minutes in Doze mode. Use `hourly` or longer intervals for reliability.
- `repeatCount` set? If you set `repeatCount: 1`, the notification only fires once (the initial fire counts).

## Action Buttons Not Appearing

**Symptom:** Notification shows but without action buttons.

**Check:**
- Each action needs at least `id` and `title`.
- Maximum actions is controlled by `config('local-notifications.max_actions')` (default 3). Extra actions are silently dropped.
- On Android, make sure you're on plugin v1.7.0+ — earlier versions had a type coercion bug that prevented action buttons from rendering.

## Permission Always Denied

**Symptom:** `requestPermission()` returns `granted: false` every time.

**Check:**
- On iOS, once the user denies permission, the system won't show the prompt again. You need to direct them to `Settings > [Your App] > Notifications`.
- On Android 13+, if the user selects "Don't ask again", the runtime prompt won't appear. Direct them to system notification settings.

## Update Returns "Notification Not Found"

**Symptom:** `LocalNotifications::update('id', [...])` returns an error.

**Check:**
- The notification must be pending (not yet fired or already canceled).
- The ID must exactly match what was used in `schedule()`.
- On Android, fired non-repeating notifications are no longer pending and can't be updated.
