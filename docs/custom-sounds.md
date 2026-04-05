# Custom Sounds

Play a custom sound file instead of the system default when a notification is delivered.

## Usage

### Array API

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

LocalNotifications::schedule([
    'id' => 'alert',
    'title' => 'Urgent Alert',
    'body' => 'Something needs attention',
    'delay' => 10,
    'soundName' => 'alert.wav',
]);
```

### Fluent Builder

```php
use Ikromjon\LocalNotifications\Notifications\LocalNotificationMessage;

// Pass the filename directly to sound()
LocalNotificationMessage::create()
    ->id('alert')
    ->title('Urgent Alert')
    ->body('Something needs attention')
    ->delay(10)
    ->sound('alert.wav');

// Or use the dedicated soundName() method
LocalNotificationMessage::create()
    ->id('alert')
    ->title('Urgent Alert')
    ->body('Something needs attention')
    ->delay(10)
    ->soundName('alert.wav');
```

### Type-Safe DTO

```php
use Ikromjon\LocalNotifications\Data\NotificationOptions;

$options = new NotificationOptions(
    id: 'alert',
    title: 'Urgent Alert',
    body: 'Something needs attention',
    delay: 10,
    soundName: 'alert.wav',
);
```

### JavaScript API

```js
import { schedule } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';

await schedule({
    id: 'alert',
    title: 'Urgent Alert',
    body: 'Something needs attention',
    delay: 10,
    soundName: 'alert.wav',
});
```

## Sound Behavior

| Parameter | Behavior |
|-----------|----------|
| Neither `sound` nor `soundName` | Uses `config('local-notifications.default_sound')` (default: `true`) |
| `sound: true` | System default notification sound |
| `sound: false` | Silent notification |
| `soundName: "alert.wav"` | Custom sound file (implies sound enabled) |

## Adding Sound Files

We recommend keeping sound files in `resources/sounds/` in your Laravel project as the canonical source. This makes them easy to discover and manage. For a working example, see the [Daily Habits](https://github.com/Ikromjon1998/daily-habits) app which auto-discovers sounds from this directory and presents them in a picker.

### Android

Copy sound files to `nativephp/android/app/src/main/res/raw/` in your NativePHP project (or the equivalent `resources/android/res/raw/` path). Android supports:

- `.wav`
- `.ogg`
- `.mp3`

The file is referenced by name **without extension**. For `soundName: "alert.wav"`, the plugin looks for a resource at `res/raw/alert`.

> **Note:** On Android O+ (API 26+), sound is set on a notification channel, not on individual notifications. The plugin automatically creates a dedicated channel per custom sound (e.g., `nativephp_local_notifications_sound_alert`). Once a channel is created, its sound cannot be changed by the app -- the user controls it in system settings.

### iOS

Place sound files in your app bundle. iOS supports:

- `.wav`
- `.aiff` / `.aif`
- `.caf`

Sound files must be **under 30 seconds**. If longer, iOS falls back to the default sound.

The file is referenced by its full filename including extension: `soundName: "alert.wav"`.

## Naming Rules

The `soundName` parameter must be a filename with extension. Only alphanumeric characters, hyphens, and underscores are allowed in the name portion:

```
alert.wav            -- valid (cross-platform)
notification_01.mp3  -- valid (cross-platform)
my_sound.caf         -- valid (cross-platform)

my-sound.caf         -- valid on iOS, but NOT on Android (hyphens are invalid in res/raw)
alert                -- invalid (no extension)
my sound.wav         -- invalid (spaces)
sounds/alert.wav     -- invalid (path separator)
```

> **Android constraint:** Resource names in `res/raw/` only allow lowercase letters, digits, and underscores. Hyphens, uppercase letters, and other special characters will cause `getIdentifier()` to return 0 at runtime, triggering the fallback to the default sound. For cross-platform compatibility, use only **lowercase letters, digits, and underscores** in the filename (e.g., `my_sound.wav` instead of `my-sound.wav`).

Invalid filenames throw an `InvalidArgumentException` at schedule time.

## Fallback Behavior

If the custom sound file is not found at runtime:

- **Android:** Falls back to the default notification channel (system sound). A warning is logged: `Custom sound resource not found: alert (res/raw/alert)`.
- **iOS:** Falls back to the default notification sound.
