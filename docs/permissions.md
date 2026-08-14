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
| `urgent` | `.critical` | **Critical alerts entitlement** (`com.apple.developer.usernotifications.critical-alerts`) — requires approval from Apple |

Without the Time Sensitive capability, iOS silently delivers `high`/`urgent` notifications as `.active` (regular banner) — no error is raised. Without the critical-alerts entitlement, the plugin detects this at schedule time and downgrades `urgent` to `.timeSensitive` automatically. Add these capabilities to your app's Xcode project/entitlements file when you rely on `high` or `urgent` priorities.

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
