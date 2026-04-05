# Rich Content

Notifications can include images, subtitles, and expanded text for a richer user experience.

## Image, Subtitle, and Expanded Text

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

LocalNotifications::schedule([
    'id' => 'promo',
    'title' => 'New Arrival',
    'body' => 'Check out our latest product',
    'subtitle' => 'Limited time offer',
    'image' => 'https://example.com/product.jpg',
    'bigText' => 'We just launched an amazing new product that you will love. Tap to learn more and get 20% off your first order!',
    'delay' => 60,
]);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `subtitle` | string | Subtitle text (iOS: subtitle, Android: subtext) |
| `image` | string | Image URL (http/https only) to display in the notification |
| `bigText` | string | Expanded body text shown when notification is expanded |

## Platform Behavior

- **iOS**: `subtitle` appears below the title. The image appears as a thumbnail that can be expanded.
- **Android**: `subtitle` appears as subtext. `bigText` replaces the body when the notification is expanded. The image appears as a large picture in the expanded view.
