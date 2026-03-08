# Epic 13: Custom Notification Icons (Android)

**Priority:** Low
**Status:** Not Started

## Description

Allow developers to customize notification icons on Android instead of always using the app icon. Android recommends a monochrome silhouette for the small icon, but the package currently uses `applicationInfo.icon` which often renders poorly in the status bar.

## Scope

- Add optional `icon` parameter to schedule options accepting a resource name (e.g., `"ic_notification"`)
- Add optional `largeIcon` parameter for the large icon (shown on the right side of the notification)
- Define a convention for where icon files should be placed (e.g., `resources/android/drawable/`) and how they get bundled into the APK via the NativePHP plugin system
- **Android:** Use `setSmallIcon(R.drawable.icon_name)` for the small icon and `setLargeIcon(bitmap)` for the large icon. Support loading large icon from a URL (download in receiver) or a local resource
- Add `setColor()` support via an optional `color` parameter (hex string) to tint the notification accent color on Android (`setColor()` on the builder)
- If no custom icon is specified, fall back to the default app icon (current behavior)
- Validate that referenced drawable resources exist at build time if possible
- Document Android icon requirements: small icon must be monochrome/white silhouette with transparency, recommended sizes (24x24dp)

## Acceptance Criteria

- [ ] Custom small icons display correctly in the status bar and notification shade
- [ ] Large icons display correctly in the notification
- [ ] Color tinting works on the notification accent
- [ ] Default app icon used when no custom icon specified
- [ ] Documentation covers icon creation requirements and file placement
