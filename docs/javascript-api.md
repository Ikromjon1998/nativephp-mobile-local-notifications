# JavaScript API

For apps using Inertia with Vue or React, import functions directly from the plugin's JavaScript library. The CSRF token is read automatically from your page's `<meta name="csrf-token">` tag.

## Import

```js
import {
    schedule,
    cancel,
    cancelAll,
    getPending,
    requestPermission,
    checkPermission,
    update,
    Events,
} from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';
```

## Functions

```js
// Request permission
const { granted } = await requestPermission();

// Schedule a notification
await schedule({
    id: 'reminder-1',
    title: 'Reminder',
    body: 'Time to take a break!',
    delay: 300,
});

// Schedule a repeating notification with actions
await schedule({
    id: 'daily-checkin',
    title: 'Daily Check-in',
    body: 'How are you feeling today?',
    at: Math.floor(Date.now() / 1000) + 60, // 1 minute from now
    repeat: 'daily',
    actions: [
        { id: 'done', title: 'Done' },
        { id: 'snooze', title: 'Snooze' },
    ],
});

// Cancel a notification
await cancel('reminder-1');

// Cancel all notifications
await cancelAll();

// List pending notifications
const { notifications, count } = await getPending();

// Check permission status
const { status } = await checkPermission();

// Update an existing notification
await update('reminder-1', { title: 'Updated!', body: 'New body text' });
```

## Function Reference

| Function | Parameters | Returns |
|----------|-----------|---------|
| `schedule(options)` | Object with `id`, `title`, `body`, and optional scheduling params | `{ success, id?, error? }` |
| `cancel(id)` | Notification ID string | `{ success, id?, error? }` |
| `cancelAll()` | None | `{ success, error? }` |
| `getPending()` | None | `{ success, notifications?, count?, error? }` |
| `requestPermission()` | None | `{ granted, status?, error? }` |
| `checkPermission()` | None | `{ status, error? }` |
| `update(id, options)` | Notification ID string, options object | `{ success, id?, error? }` |

## Listening to Events

Use the NativePHP `On()` function with the plugin's `Events` constants:

```js
import { On } from '#nativephp';
import { Events } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';

// User tapped a notification
On(Events.NotificationTapped, (payload) => {
    console.log('Tapped:', payload.id, payload.data);
});

// Notification was delivered
On(Events.NotificationReceived, (payload) => {
    console.log('Received:', payload.id);
});

// Action button pressed
On(Events.NotificationActionPressed, (payload) => {
    console.log('Action:', payload.actionId, payload.inputText);
});

// Permission result
On(Events.PermissionGranted, () => console.log('Permission granted'));
On(Events.PermissionDenied, () => console.log('Permission denied'));
```

## Event Constants

| Constant | PHP Event Class |
|----------|----------------|
| `Events.NotificationScheduled` | `NotificationScheduled` |
| `Events.NotificationReceived` | `NotificationReceived` |
| `Events.NotificationTapped` | `NotificationTapped` |
| `Events.NotificationActionPressed` | `NotificationActionPressed` |
| `Events.NotificationUpdated` | `NotificationUpdated` |
| `Events.PermissionGranted` | `PermissionGranted` |
| `Events.PermissionDenied` | `PermissionDenied` |
