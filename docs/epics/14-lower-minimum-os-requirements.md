# Epic 14: Lower Minimum OS Requirements

**Priority:** Low
**Status:** Not Started

## Description

Expand device compatibility by supporting older OS versions where possible. Currently, the package requires iOS 18.2+ and Android API 33+ (Android 13), which excludes a significant portion of devices unnecessarily since the underlying notification APIs have been available since much earlier versions.

## Scope

- **Android:** Lower minimum API from 33 to 26 (Android 8.0)
  - API 26-32: Notifications are allowed by default (no runtime `POST_NOTIFICATIONS` permission needed). Conditionally skip the permission request on these versions
  - API 26+: `NotificationChannel` is required and already used
  - Handle `SCHEDULE_EXACT_ALARM` permission only on API 31+ (conditionally)
  - Use `AlarmManagerCompat` from AndroidX for backward-compatible exact alarm scheduling
  - Test on API 26, 28, 30, 31, 33 emulators
- **iOS:** Lower minimum from 18.2 to 16.0 (if NativePHP Mobile supports it)
  - iOS 16+: All `UNUserNotificationCenter` APIs used are available since iOS 10
  - Use `#available` checks for any APIs that require newer iOS versions
  - Test the `interruptionLevel` feature from Epic 9 with `#available(iOS 15.0, *)` guard
- Update `nativephp.json` with the new minimum versions
- Add CI matrix testing across multiple OS versions

## Acceptance Criteria

- [ ] Package works on Android 8.0+ (API 26+)
- [ ] Package works on iOS 16.0+ (if NativePHP supports it)
- [ ] No crashes or runtime errors on older OS versions
- [ ] Permission handling adapts correctly to OS version
- [ ] CI tests pass on minimum supported OS versions
