import Foundation
import UserNotifications
import UIKit

// MARK: - Notification Delegate

/// Handles notification delivery and user interaction events.
/// Set as the UNUserNotificationCenter delegate to dispatch
/// NotificationReceived and NotificationTapped events.
class LocalNotificationDelegate: NSObject, UNUserNotificationCenterDelegate {
    static let shared = LocalNotificationDelegate()
    private static var isRegistered = false

    static func ensureRegistered() {
        if !isRegistered {
            UNUserNotificationCenter.current().delegate = shared
            isRegistered = true
        }
    }

    /// Called when a notification is delivered while the app is in the foreground.
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        let content = notification.request.content
        let userInfo = content.userInfo
        let id = userInfo["notification_id"] as? String ?? notification.request.identifier

        let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationReceived"
        var payload: [String: Any] = ["id": id, "title": content.title, "body": content.body]

        let customData = extractCustomData(from: userInfo)
        if !customData.isEmpty {
            payload["data"] = customData
        }

        LaravelBridge.shared.send?(eventClass, payload)

        // Show the notification banner even when the app is in the foreground
        completionHandler([.banner, .sound, .badge])
    }

    /// Called when the user taps a notification or presses an action button.
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let content = response.notification.request.content
        let userInfo = content.userInfo
        let id = userInfo["notification_id"] as? String ?? response.notification.request.identifier
        let customData = extractCustomData(from: userInfo)

        if response.actionIdentifier == UNNotificationDefaultActionIdentifier
            || response.actionIdentifier == UNNotificationDismissActionIdentifier {
            // Default tap on the notification body
            if response.actionIdentifier == UNNotificationDefaultActionIdentifier {
                let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
                var payload: [String: Any] = ["id": id, "title": content.title, "body": content.body]
                if !customData.isEmpty { payload["data"] = customData }
                LaravelBridge.shared.send?(eventClass, payload)
            }
        } else {
            // Custom action button pressed
            let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed"
            var payload: [String: Any] = [
                "notificationId": id,
                "actionId": response.actionIdentifier
            ]
            if !customData.isEmpty { payload["data"] = customData }

            // Include text input if this was a text input action
            if let textResponse = response as? UNTextInputNotificationResponse {
                payload["inputText"] = textResponse.userText
            }

            LaravelBridge.shared.send?(eventClass, payload)
        }

        completionHandler()
    }

    /// Extracts custom data from userInfo, excluding internal keys.
    private func extractCustomData(from userInfo: [AnyHashable: Any]) -> [String: Any] {
        var customData: [String: Any] = [:]
        for (key, value) in userInfo {
            if let key = key as? String, key != "notification_id" {
                customData[key] = value
            }
        }
        return customData
    }
}

// MARK: - LocalNotifications Function Namespace

enum LocalNotificationsFunctions {

    // MARK: - LocalNotifications.Schedule

    /// Schedule a local notification
    /// Parameters:
    ///   - id: string - Unique identifier for this notification
    ///   - title: string - Notification title
    ///   - body: string - Notification body text
    ///   - delay: (optional) int - Delay in seconds from now
    ///   - at: (optional) int - Unix timestamp to fire at
    ///   - repeat: (optional) string - Repeat interval: "minute", "hourly", "daily", "weekly"
    ///   - sound: (optional) boolean - Play sound (default: true)
    ///   - badge: (optional) int - Badge number to set on app icon
    ///   - data: (optional) object - Custom data to attach to the notification
    ///   - subtitle: (optional) string - Notification subtitle
    ///   - image: (optional) string - URL of an image to attach
    ///   - bigText: (optional) string - Expanded body text
    ///   - actions: (optional) array - Action buttons [{id, title, destructive?, input?}] (max 3)
    /// Returns:
    ///   - success: boolean
    /// Events:
    ///   - Fires NotificationScheduled when notification is successfully scheduled
    class Schedule: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            // Ensure the notification delegate is registered
            LocalNotificationDelegate.ensureRegistered()

            guard let id = parameters["id"] as? String else {
                return ["success": false, "error": "Missing required parameter: id"]
            }
            guard let title = parameters["title"] as? String else {
                return ["success": false, "error": "Missing required parameter: title"]
            }
            guard let body = parameters["body"] as? String else {
                return ["success": false, "error": "Missing required parameter: body"]
            }

            let sound = parameters["sound"] as? Bool ?? true
            let badge = parameters["badge"] as? Int
            let data = parameters["data"] as? [String: Any]
            let repeatInterval = parameters["repeat"] as? String
            let subtitle = parameters["subtitle"] as? String
            let imageUrl = parameters["image"] as? String
            let bigText = parameters["bigText"] as? String

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

            // Register action buttons if provided
            if let actionsArray = parameters["actions"] as? [[String: Any]], !actionsArray.isEmpty {
                let categoryId = "NOTIF_ACTIONS_\(id)"
                let actions: [UNNotificationAction] = actionsArray.prefix(3).map { actionDict in
                    let actionId = actionDict["id"] as? String ?? ""
                    let actionTitle = actionDict["title"] as? String ?? ""
                    let isDestructive = actionDict["destructive"] as? Bool ?? false
                    let isInput = actionDict["input"] as? Bool ?? false

                    if isInput {
                        var options: UNNotificationActionOptions = []
                        if isDestructive { options.insert(.destructive) }
                        return UNTextInputNotificationAction(
                            identifier: actionId,
                            title: actionTitle,
                            options: options
                        )
                    } else {
                        var options: UNNotificationActionOptions = []
                        if isDestructive { options.insert(.destructive) }
                        return UNNotificationAction(
                            identifier: actionId,
                            title: actionTitle,
                            options: options
                        )
                    }
                }

                let category = UNNotificationCategory(
                    identifier: categoryId,
                    actions: actions,
                    intentIdentifiers: [],
                    options: []
                )
                let center = UNUserNotificationCenter.current()
                // Merge with existing categories
                let semaphoreCategories = DispatchSemaphore(value: 0)
                center.getNotificationCategories { existingCategories in
                    var categories = existingCategories
                    // Remove old category with same id if exists
                    categories = categories.filter { $0.identifier != categoryId }
                    categories.insert(category)
                    center.setNotificationCategories(categories)
                    semaphoreCategories.signal()
                }
                semaphoreCategories.wait()

                content.categoryIdentifier = categoryId
            }

            // Attach image if provided
            if let imageUrl = imageUrl, let url = URL(string: imageUrl) {
                if let attachment = Self.downloadAndAttachImage(from: url) {
                    content.attachments = [attachment]
                } else {
                    print("⚠️ Failed to download image, sending notification without image")
                }
            }

            // Determine trigger
            var trigger: UNNotificationTrigger?

            if let delay = parameters["delay"] as? Int, delay > 0 {
                let repeats = repeatInterval != nil
                trigger = UNTimeIntervalNotificationTrigger(
                    timeInterval: TimeInterval(delay),
                    repeats: repeats
                )
            } else if let timestamp = parameters["at"] as? Int {
                let date = Date(timeIntervalSince1970: TimeInterval(timestamp))
                var dateComponents = Calendar.current.dateComponents(
                    [.year, .month, .day, .hour, .minute, .second],
                    from: date
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

                trigger = UNCalendarNotificationTrigger(
                    dateMatching: dateComponents,
                    repeats: repeats
                )
            } else {
                // Fire immediately (1 second delay)
                trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
            }

            let request = UNNotificationRequest(
                identifier: id,
                content: content,
                trigger: trigger
            )

            let center = UNUserNotificationCenter.current()

            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.add(request) { error in
                if let error = error {
                    print("❌ Failed to schedule notification: \(error.localizedDescription)")
                    result = ["success": false, "error": error.localizedDescription]
                } else {
                    print("✅ Notification scheduled: \(id)")
                    result = ["success": true, "id": id]

                    let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled"
                    let payload: [String: Any] = ["id": id, "title": title, "body": body]
                    LaravelBridge.shared.send?(eventClass, payload)
                }
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }

        /// Downloads an image from a URL and creates a UNNotificationAttachment.
        private static func downloadAndAttachImage(from url: URL) -> UNNotificationAttachment? {
            let semaphore = DispatchSemaphore(value: 0)
            var attachment: UNNotificationAttachment?

            let task = URLSession.shared.downloadTask(with: url) { localUrl, response, error in
                defer { semaphore.signal() }

                guard let localUrl = localUrl, error == nil else {
                    print("❌ Image download failed: \(error?.localizedDescription ?? "unknown error")")
                    return
                }

                // Determine file extension from response or URL
                let ext = url.pathExtension.isEmpty ? "jpg" : url.pathExtension
                let tmpDir = FileManager.default.temporaryDirectory
                let tmpFile = tmpDir.appendingPathComponent(UUID().uuidString + "." + ext)

                do {
                    try FileManager.default.moveItem(at: localUrl, to: tmpFile)
                    attachment = try UNNotificationAttachment(
                        identifier: UUID().uuidString,
                        url: tmpFile,
                        options: nil
                    )
                } catch {
                    print("❌ Failed to create attachment: \(error.localizedDescription)")
                }
            }
            task.resume()
            semaphore.wait()

            return attachment
        }
    }

    // MARK: - LocalNotifications.Cancel

    /// Cancel a scheduled notification by identifier
    /// Parameters:
    ///   - id: string - The notification identifier to cancel
    /// Returns:
    ///   - success: boolean
    class Cancel: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String else {
                return ["success": false, "error": "Missing required parameter: id"]
            }

            let center = UNUserNotificationCenter.current()
            center.removePendingNotificationRequests(withIdentifiers: [id])
            center.removeDeliveredNotifications(withIdentifiers: [id])

            print("✅ Notification cancelled: \(id)")
            return ["success": true, "id": id]
        }
    }

    // MARK: - LocalNotifications.CancelAll

    /// Cancel all scheduled notifications
    /// Returns:
    ///   - success: boolean
    class CancelAll: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let center = UNUserNotificationCenter.current()
            center.removeAllPendingNotificationRequests()
            center.removeAllDeliveredNotifications()

            print("✅ All notifications cancelled")
            return ["success": true]
        }
    }

    // MARK: - LocalNotifications.GetPending

    /// Get all pending scheduled notifications
    /// Returns:
    ///   - notifications: JSON string array of pending notifications
    class GetPending: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.getPendingNotificationRequests { requests in
                let notifications = requests.map { request -> [String: Any] in
                    var notification: [String: Any] = [
                        "id": request.identifier,
                        "title": request.content.title,
                        "body": request.content.body
                    ]

                    if let trigger = request.trigger as? UNCalendarNotificationTrigger {
                        notification["repeats"] = trigger.repeats
                        if let nextDate = trigger.nextTriggerDate() {
                            notification["nextTriggerAt"] = Int(nextDate.timeIntervalSince1970)
                        }
                    } else if let trigger = request.trigger as? UNTimeIntervalNotificationTrigger {
                        notification["repeats"] = trigger.repeats
                        notification["timeInterval"] = Int(trigger.timeInterval)
                        if let nextDate = trigger.nextTriggerDate() {
                            notification["nextTriggerAt"] = Int(nextDate.timeIntervalSince1970)
                        }
                    }

                    return notification
                }

                do {
                    let jsonData = try JSONSerialization.data(withJSONObject: notifications, options: [])
                    let jsonString = String(data: jsonData, encoding: .utf8) ?? "[]"
                    result = ["success": true, "notifications": jsonString, "count": notifications.count]
                } catch {
                    result = ["success": false, "error": error.localizedDescription]
                }

                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }

    // MARK: - LocalNotifications.RequestPermission

    /// Request notification permission
    /// Returns:
    ///   - granted: boolean
    /// Events:
    ///   - Fires PermissionGranted or PermissionDenied
    class RequestPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            // Ensure the notification delegate is registered
            LocalNotificationDelegate.ensureRegistered()

            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
                if let error = error {
                    print("❌ Permission request error: \(error.localizedDescription)")
                    result = ["granted": false, "error": error.localizedDescription]

                    let eventClass = "Ikromjon\\LocalNotifications\\Events\\PermissionDenied"
                    LaravelBridge.shared.send?(eventClass, [:])
                } else if granted {
                    print("✅ Notification permission granted")
                    result = ["granted": true]

                    let eventClass = "Ikromjon\\LocalNotifications\\Events\\PermissionGranted"
                    LaravelBridge.shared.send?(eventClass, [:])
                } else {
                    print("❌ Notification permission denied")
                    result = ["granted": false]

                    let eventClass = "Ikromjon\\LocalNotifications\\Events\\PermissionDenied"
                    LaravelBridge.shared.send?(eventClass, [:])
                }

                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }

    // MARK: - LocalNotifications.CheckPermission

    /// Check current notification permission status
    /// Returns:
    ///   - status: string - "granted", "denied", "notDetermined", "provisional"
    class CheckPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.getNotificationSettings { settings in
                let status: String
                switch settings.authorizationStatus {
                case .authorized:
                    status = "granted"
                case .denied:
                    status = "denied"
                case .notDetermined:
                    status = "notDetermined"
                case .provisional:
                    status = "provisional"
                case .ephemeral:
                    status = "ephemeral"
                @unknown default:
                    status = "unknown"
                }

                result = ["status": status]
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }
}
