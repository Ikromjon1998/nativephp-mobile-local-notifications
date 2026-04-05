import Foundation
import UserNotifications

/// Shared notification utilities used by Schedule and Update to eliminate
/// duplicated content building, action registration, image attachment,
/// and trigger construction.
enum NotificationHelper {

    // MARK: - Content Building

    /// Build a UNMutableNotificationContent from common parameters.
    static func buildContent(
        id: String,
        title: String,
        body: String,
        subtitle: String?,
        sound: Bool,
        badge: Int?,
        data: [String: Any]?
    ) -> UNMutableNotificationContent {
        let content = UNMutableNotificationContent()
        content.title = title
        content.body = body

        if let subtitle = subtitle {
            content.subtitle = subtitle
        }

        if sound {
            content.sound = .default
        }

        if let badge = badge {
            content.badge = NSNumber(value: badge)
        }

        var userInfo: [String: Any] = ["notification_id": id]
        if let data = data {
            for (key, value) in data {
                userInfo[key] = value
            }
        }
        content.userInfo = userInfo

        return content
    }

    // MARK: - Action Buttons

    /// Register action buttons as a UNNotificationCategory and set the
    /// categoryIdentifier on the content. Returns the updated content.
    /// Stores snooze durations in userInfo so the delegate can reschedule on action press.
    @discardableResult
    static func registerActions(
        id: String,
        actionsArray: [[String: Any]],
        maxActions: Int,
        content: UNMutableNotificationContent
    ) -> UNMutableNotificationContent {
        let categoryId = "NOTIF_ACTIONS_\(id)"
        var snoozeDurations: [String: Int] = [:]

        let actions: [UNNotificationAction] = actionsArray.prefix(maxActions).map { actionDict in
            let actionId = actionDict["id"] as? String ?? ""
            let actionTitle = actionDict["title"] as? String ?? ""
            let isDestructive = actionDict["destructive"] as? Bool ?? false
            let isInput = actionDict["input"] as? Bool ?? false

            if let snooze = actionDict["snooze"] as? Int, snooze > 0 {
                snoozeDurations[actionId] = snooze
            }

            if isInput {
                var options: UNNotificationActionOptions = []
                if isDestructive { options.insert(.destructive) }
                return UNTextInputNotificationAction(
                    identifier: actionId, title: actionTitle, options: options
                )
            } else {
                var options: UNNotificationActionOptions = []
                if isDestructive { options.insert(.destructive) }
                return UNNotificationAction(
                    identifier: actionId, title: actionTitle, options: options
                )
            }
        }

        // Store snooze durations in userInfo so didReceive can access them
        if !snoozeDurations.isEmpty {
            var userInfo = content.userInfo
            userInfo["action_snooze"] = snoozeDurations
            content.userInfo = userInfo
        }

        let category = UNNotificationCategory(
            identifier: categoryId, actions: actions,
            intentIdentifiers: [], options: []
        )
        let center = UNUserNotificationCenter.current()
        let semaphore = DispatchSemaphore(value: 0)
        center.getNotificationCategories { existingCategories in
            var categories = existingCategories.filter { $0.identifier != categoryId }
            categories.insert(category)
            center.setNotificationCategories(categories)
            semaphore.signal()
        }
        semaphore.wait()

        content.categoryIdentifier = categoryId
        return content
    }

    // MARK: - Image Attachment

    /// Attach an image from a URL to the notification content.
    /// Only http/https URLs are allowed (prevents SSRF).
    static func attachImage(to content: UNMutableNotificationContent, imageUrl: String?) {
        guard let imageUrl = imageUrl,
              let url = URL(string: imageUrl),
              let scheme = url.scheme?.lowercased(),
              scheme == "http" || scheme == "https" else {
            if imageUrl != nil {
                print("⚠️ Rejected image URL with unsupported scheme, only http/https allowed")
            }
            return
        }

        if let attachment = downloadAndAttachImage(from: url) {
            content.attachments = [attachment]
        } else {
            print("⚠️ Failed to download image, sending notification without image")
        }
    }

    /// Downloads an image from a URL and creates a UNNotificationAttachment.
    static func downloadAndAttachImage(from url: URL) -> UNNotificationAttachment? {
        let semaphore = DispatchSemaphore(value: 0)
        var attachment: UNNotificationAttachment?

        let task = URLSession.shared.downloadTask(with: url) { localUrl, response, error in
            defer { semaphore.signal() }
            guard let localUrl = localUrl, error == nil else {
                print("❌ Image download failed: \(error?.localizedDescription ?? "unknown error")")
                return
            }
            let ext = url.pathExtension.isEmpty ? "jpg" : url.pathExtension
            let tmpFile = FileManager.default.temporaryDirectory
                .appendingPathComponent(UUID().uuidString + "." + ext)
            do {
                try FileManager.default.moveItem(at: localUrl, to: tmpFile)
                attachment = try UNNotificationAttachment(
                    identifier: UUID().uuidString, url: tmpFile, options: nil
                )
            } catch {
                print("❌ Failed to create attachment: \(error.localizedDescription)")
            }
        }
        task.resume()
        semaphore.wait()
        return attachment
    }

    // MARK: - Trigger Building

    /// Build a UNNotificationTrigger from scheduling parameters.
    ///
    /// - Parameters:
    ///   - delay: Seconds from now (mutually exclusive with `at`)
    ///   - at: Unix timestamp
    ///   - repeatInterval: Named interval (minute, hourly, daily, weekly, monthly, yearly)
    ///   - repeatIntervalSeconds: Custom interval in seconds (>=60)
    /// - Returns: A configured trigger, or a 1-second fire-immediately trigger
    static func buildTrigger(
        delay: Int?,
        at timestamp: Int?,
        repeatInterval: String?,
        repeatIntervalSeconds: Int?
    ) -> UNNotificationTrigger {
        if let customSeconds = repeatIntervalSeconds, customSeconds >= 60, repeatInterval == nil {
            return UNTimeIntervalNotificationTrigger(
                timeInterval: TimeInterval(customSeconds), repeats: true
            )
        }

        if let delay = delay, delay > 0 {
            let repeats = repeatInterval != nil
            return UNTimeIntervalNotificationTrigger(
                timeInterval: TimeInterval(delay), repeats: repeats
            )
        }

        if let timestamp = timestamp {
            let date = Date(timeIntervalSince1970: TimeInterval(timestamp))
            var dateComponents = Calendar.current.dateComponents(
                [.year, .month, .day, .hour, .minute, .second], from: date
            )
            var repeats = false

            if let interval = repeatInterval {
                repeats = true
                switch interval {
                case "minute":
                    dateComponents = Calendar.current.dateComponents([.second], from: date)
                case "hourly":
                    dateComponents = Calendar.current.dateComponents([.minute, .second], from: date)
                case "daily":
                    dateComponents = Calendar.current.dateComponents([.hour, .minute, .second], from: date)
                case "weekly":
                    dateComponents = Calendar.current.dateComponents([.weekday, .hour, .minute, .second], from: date)
                case "monthly":
                    dateComponents = Calendar.current.dateComponents([.day, .hour, .minute, .second], from: date)
                case "yearly":
                    dateComponents = Calendar.current.dateComponents([.month, .day, .hour, .minute, .second], from: date)
                default:
                    repeats = false
                }
            }

            return UNCalendarNotificationTrigger(dateMatching: dateComponents, repeats: repeats)
        }

        // Fire immediately (1 second delay)
        return UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
    }

    // MARK: - Day-of-Week Scheduling

    /// Schedule day-of-week sub-requests. Creates one UNNotificationRequest per ISO day,
    /// each with a weekly calendar trigger at the time derived from `timestamp`.
    ///
    /// - Returns: Error if any request fails, nil on success
    static func scheduleDayOfWeekRequests(
        id: String,
        days: [Int],
        timestamp: Int,
        content: UNMutableNotificationContent,
        repeatCount: Int?
    ) -> Error? {
        let date = Date(timeIntervalSince1970: TimeInterval(timestamp))
        let center = UNUserNotificationCenter.current()
        let semaphore = DispatchSemaphore(value: 0)
        var lastError: Error?

        for isoDay in days {
            let appleWeekday = isoDay == 7 ? 1 : isoDay + 1
            let subId = "\(id)_day_\(isoDay)"

            var dateComponents = Calendar.current.dateComponents(
                [.hour, .minute, .second], from: date
            )
            dateComponents.weekday = appleWeekday

            let trigger = UNCalendarNotificationTrigger(
                dateMatching: dateComponents, repeats: true
            )
            let request = UNNotificationRequest(
                identifier: subId, content: content, trigger: trigger
            )

            center.add(request) { error in
                if let error = error { lastError = error }
                semaphore.signal()
            }
            semaphore.wait()
        }

        // Store repeat count for each sub-ID
        if let count = repeatCount, count >= 1 {
            let defaults = UserDefaults.standard
            for isoDay in days {
                defaults.set(count, forKey: "notif_remaining_\(id)_day_\(isoDay)")
            }
        }

        return lastError
    }

    // MARK: - Custom Data Extraction

    /// Extract custom data from userInfo, excluding internal keys.
    static func extractCustomData(from userInfo: [AnyHashable: Any]) -> [String: Any] {
        var customData: [String: Any] = [:]
        for (key, value) in userInfo {
            if let key = key as? String, key != "notification_id" {
                customData[key] = value
            }
        }
        return customData
    }
}
