# Epic 3: Rich Notification Content

**Priority:** High
**Status:** Done

## Description

Support images, large text, and media attachments in notifications. Currently, notifications only support plain text title and body with no visual media.

## Scope

- Add optional `image` parameter (URL or local file path) to the schedule options
- **iOS:** Use `UNNotificationAttachment` to attach downloaded images to the notification content. Download the image to a temporary file and create an attachment with the appropriate UTI
- **Android:** Use `NotificationCompat.BigPictureStyle` to display large images in the expanded notification view. Download images using a background-safe mechanism and set as the big picture bitmap
- Add optional `largeBody` / `bigText` parameter for expanded text content. Use `NotificationCompat.BigTextStyle` on Android and set the body on iOS (which supports longer text natively)
- Add optional `subtitle` parameter (iOS: `subtitle` property on `UNMutableNotificationContent`, Android: `setSubText()` on the notification builder)
- Handle image download failures gracefully — fall back to a standard text notification if the image cannot be fetched
- Update the PHP `schedule()` method validation to accept the new parameters

## Acceptance Criteria

- [ ] Notifications can display images from both remote URLs and local file paths
- [ ] Expanded text works on both platforms
- [ ] Subtitle displays correctly on both platforms
- [ ] Graceful fallback when image download fails
