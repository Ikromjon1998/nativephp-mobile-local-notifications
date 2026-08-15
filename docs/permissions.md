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

### Entitlements for priority levels

The `priority` scheduling option maps to iOS interruption levels that need app capabilities beyond runtime authorization:

| Priority | Interruption level | Requirement |
|----------|--------------------|-------------|
| `high` | `.timeSensitive` | **Time Sensitive Notifications** capability (`com.apple.developer.usernotifications.time-sensitive`) in the app's entitlements |
| `urgent` | `.critical` | **Critical alerts entitlement** (`com.apple.developer.usernotifications.critical-alerts`, requires approval from Apple) **and** critical-alert authorization requested at runtime via `requestPermission(critical: true)` |

Without the Time Sensitive capability, iOS silently delivers `high`/`urgent` notifications as `.active` (regular banner) — no error is raised. For critical alerts, the entitlement alone is not enough: iOS only enables critical alerts after the app requests critical-alert authorization, so call `requestPermission(critical: true)` once (only in entitled apps). If critical alerts are not enabled — missing entitlement or authorization not requested — the plugin detects this at schedule time and downgrades `urgent` to `.timeSensitive` automatically.

## Environment Variables

None required. The plugin works entirely on-device with no external services.

## Requesting Permission

Permission must be requested before notifications can be shown on Android 13+ and iOS.

**PHP:**
```php
$result = LocalNotifications::requestPermission();
// Returns: ['granted' => true] or ['granted' => false, 'status' => 'pending']

// Apps holding Apple's critical-alerts entitlement can additionally request
// critical-alert authorization (needed for priority 'urgent'; ignored on Android):
$result = LocalNotifications::requestPermission(critical: true);
```

**JavaScript:**
```js
const { granted } = await requestPermission();
// With critical-alert authorization (entitled apps only):
const { granted } = await requestPermission({ critical: true });
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
