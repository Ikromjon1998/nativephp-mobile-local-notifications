# Permissions

The plugin declares all required permissions automatically via `nativephp.json`. No manual configuration needed.

## Android

| Permission | Purpose |
|-----------|---------|
| `POST_NOTIFICATIONS` | Show notifications (Android 13+, requested at runtime) |
| `SCHEDULE_EXACT_ALARM` | Schedule notifications at exact times |
| `USE_EXACT_ALARM` | Fallback for exact alarm scheduling |
| `RECEIVE_BOOT_COMPLETED` | Restore scheduled notifications after device reboot |
| `VIBRATE` | Vibrate on notification delivery |

## iOS

- Notification authorization is requested at runtime via `requestPermission()` (alert, sound, badge)
- Minimum iOS version: 18.0 (NativePHP baseline)

## Environment Variables

None required. The plugin works entirely on-device with no external services.

## Requesting Permission

Permission must be requested before notifications can be shown on Android 13+ and iOS.

**PHP:**
```php
$result = LocalNotifications::requestPermission();
// Returns: ['granted' => true] or ['granted' => false, 'status' => 'pending']
```

**JavaScript:**
```js
const { granted } = await requestPermission();
```

Listen for the result with `PermissionGranted` or `PermissionDenied` events. See [Events](events.md) for details.

## Checking Permission Status

**PHP:**
```php
$result = LocalNotifications::checkPermission();
// Returns: ['status' => 'granted'] or ['status' => 'denied']
```

**JavaScript:**
```js
const { status } = await checkPermission();
```
