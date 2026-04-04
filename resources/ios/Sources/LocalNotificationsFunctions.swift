import Foundation
import UserNotifications
import UIKit

// MARK: - Notification Delegate

/// Handles notification delivery and user interaction events.
class LocalNotificationDelegate: NSObject, UNUserNotificationCenterDelegate {
    static let shared = LocalNotificationDelegate()
    private static var isRegistered = false

    private var pendingEvents: [(eventClass: String, payload: [String: Any])] = []
    private let pendingQueue = DispatchQueue(label: "com.nativephp.localnotifications.pending")

    static func ensureRegistered() {
        if !isRegistered {
            UNUserNotificationCenter.current().delegate = shared
            isRegistered = true
        }
    }

    private func sendOrQueue(eventClass: String, payload: [String: Any]) {
        if let send = LaravelBridge.shared.send {
            send(eventClass, payload)
        } else {
            pendingQueue.sync {
                pendingEvents.append((eventClass: eventClass, payload: payload))
                print("⏳ Queued pending event: \(eventClass), queue size: \(pendingEvents.count)")
            }
        }
    }

    func dispatchPendingEvents() {
        var events: [(eventClass: String, payload: [String: Any])] = []
        pendingQueue.sync {
            events = pendingEvents
            pendingEvents.removeAll()
        }
        guard !events.isEmpty, let send = LaravelBridge.shared.send else { return }
        print("📤 Dispatching \(events.count) pending event(s)")
        for event in events {
            send(event.eventClass, event.payload)
        }
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        let content = notification.request.content
        let userInfo = content.userInfo
        let requestId = notification.request.identifier
        let id = userInfo["notification_id"] as? String ?? requestId

        // Decrement repeat count
        let remainingKey = "notif_remaining_\(requestId)"
        let defaults = UserDefaults.standard
        let remaining = defaults.integer(forKey: remainingKey)
        if remaining > 0 {
            if remaining <= 1 {
                center.removePendingNotificationRequests(withIdentifiers: [requestId])
                defaults.removeObject(forKey: remainingKey)
            } else {
                defaults.set(remaining - 1, forKey: remainingKey)
            }
        }

        let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationReceived"
        var payload: [String: Any] = ["id": id, "title": content.title, "body": content.body]
        let customData = NotificationHelper.extractCustomData(from: userInfo)
        if !customData.isEmpty { payload["data"] = customData }

        sendOrQueue(eventClass: eventClass, payload: payload)
        completionHandler([.banner, .sound, .badge])
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let content = response.notification.request.content
        let userInfo = content.userInfo
        let requestId = response.notification.request.identifier
        let id = userInfo["notification_id"] as? String ?? requestId
        let customData = NotificationHelper.extractCustomData(from: userInfo)

        // Decrement repeat count
        let remainingKey = "notif_remaining_\(requestId)"
        let defaults = UserDefaults.standard
        let remaining = defaults.integer(forKey: remainingKey)
        if remaining > 0 {
            if remaining <= 1 {
                center.removePendingNotificationRequests(withIdentifiers: [requestId])
                defaults.removeObject(forKey: remainingKey)
            } else {
                defaults.set(remaining - 1, forKey: remainingKey)
            }
        }

        if response.actionIdentifier == UNNotificationDefaultActionIdentifier
            || response.actionIdentifier == UNNotificationDismissActionIdentifier {
            if response.actionIdentifier == UNNotificationDefaultActionIdentifier {
                let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
                var payload: [String: Any] = ["id": id, "title": content.title, "body": content.body]
                if !customData.isEmpty { payload["data"] = customData }
                sendOrQueue(eventClass: eventClass, payload: payload)
            }
        } else {
            let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed"
            var payload: [String: Any] = ["notificationId": id, "actionId": response.actionIdentifier]
            if !customData.isEmpty { payload["data"] = customData }
            if let textResponse = response as? UNTextInputNotificationResponse {
                payload["inputText"] = textResponse.userText
            }
            sendOrQueue(eventClass: eventClass, payload: payload)
        }

        completionHandler()
    }
}

// MARK: - Bridge Functions

enum LocalNotificationsFunctions {

    /// Common setup for every bridge function.
    private static func initBridgeCall() {
        LocalNotificationDelegate.ensureRegistered()
        LocalNotificationDelegate.shared.dispatchPendingEvents()
    }

    // MARK: - Schedule

    class Schedule: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            guard let id = parameters["id"] as? String else {
                return ["success": false, "error": "Missing required parameter: id"]
            }
            guard let title = parameters["title"] as? String else {
                return ["success": false, "error": "Missing required parameter: title"]
            }
            guard let body = parameters["body"] as? String else {
                return ["success": false, "error": "Missing required parameter: body"]
            }

            let config = parameters["_config"] as? [String: Any]
            let defaultSound = config?["default_sound"] as? Bool ?? true
            let maxActions = max(1, (config?["max_actions"] as? Int) ?? 3)

            let sound = parameters["sound"] as? Bool ?? defaultSound
            let badge = parameters["badge"] as? Int
            let data = parameters["data"] as? [String: Any]
            let subtitle = parameters["subtitle"] as? String
            let imageUrl = parameters["image"] as? String
            let repeatInterval = parameters["repeat"] as? String
            let repeatIntervalSeconds = parameters["repeatIntervalSeconds"] as? Int
            let repeatDays = parameters["repeatDays"] as? [Int]
            let repeatCount = parameters["repeatCount"] as? Int

            let content = NotificationHelper.buildContent(
                id: id, title: title, body: body,
                subtitle: subtitle, sound: sound, badge: badge, data: data
            )

            // Action buttons
            if let actionsArray = parameters["actions"] as? [[String: Any]], !actionsArray.isEmpty {
                NotificationHelper.registerActions(id: id, actionsArray: actionsArray, maxActions: maxActions, content: content)
            }

            // Image attachment
            NotificationHelper.attachImage(to: content, imageUrl: imageUrl)

            // Day-of-week scheduling
            if let days = repeatDays, !days.isEmpty, let timestamp = parameters["at"] as? Int {
                if let error = NotificationHelper.scheduleDayOfWeekRequests(
                    id: id, days: days, timestamp: timestamp,
                    content: content, repeatCount: repeatCount
                ) {
                    return ["success": false, "error": error.localizedDescription]
                }

                let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled"
                LaravelBridge.shared.send?(eventClass, ["id": id, "title": title, "body": body])
                return ["success": true, "id": id]
            }

            // Standard scheduling
            let trigger = NotificationHelper.buildTrigger(
                delay: parameters["delay"] as? Int,
                at: parameters["at"] as? Int,
                repeatInterval: repeatInterval,
                repeatIntervalSeconds: repeatIntervalSeconds
            )

            let request = UNNotificationRequest(identifier: id, content: content, trigger: trigger)
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

                    if let count = repeatCount, count >= 1 {
                        UserDefaults.standard.set(count, forKey: "notif_remaining_\(id)")
                    }

                    let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled"
                    LaravelBridge.shared.send?(eventClass, ["id": id, "title": title, "body": body])
                }
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }

    // MARK: - Cancel

    class Cancel: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            guard let id = parameters["id"] as? String else {
                return ["success": false, "error": "Missing required parameter: id"]
            }

            let center = UNUserNotificationCenter.current()

            // Cancel direct ID
            center.removePendingNotificationRequests(withIdentifiers: [id])
            center.removeDeliveredNotifications(withIdentifiers: [id])
            UserDefaults.standard.removeObject(forKey: "notif_remaining_\(id)")

            // Cancel any day-of-week sub-IDs
            let subIds = (1...7).map { "\(id)_day_\($0)" }
            center.removePendingNotificationRequests(withIdentifiers: subIds)
            center.removeDeliveredNotifications(withIdentifiers: subIds)
            for subId in subIds {
                UserDefaults.standard.removeObject(forKey: "notif_remaining_\(subId)")
            }

            print("✅ Notification cancelled: \(id)")
            return ["success": true, "id": id]
        }
    }

    // MARK: - CancelAll

    class CancelAll: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            let center = UNUserNotificationCenter.current()
            center.removeAllPendingNotificationRequests()
            center.removeAllDeliveredNotifications()

            print("✅ All notifications cancelled")
            return ["success": true]
        }
    }

    // MARK: - GetPending

    class GetPending: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.getPendingNotificationRequests { requests in
                var regularNotifications: [[String: Any]] = []
                var dayGroups: [String: (request: UNNotificationRequest, days: [Int])] = [:]
                let defaults = UserDefaults.standard

                for request in requests {
                    let id = request.identifier
                    if let range = id.range(of: "_day_"), let day = Int(id[range.upperBound...]) {
                        let parentId = String(id[..<range.lowerBound])
                        if var group = dayGroups[parentId] {
                            group.days.append(day)
                            dayGroups[parentId] = group
                        } else {
                            dayGroups[parentId] = (request: request, days: [day])
                        }
                    } else {
                        var notification: [String: Any] = [
                            "id": id, "title": request.content.title, "body": request.content.body
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

                        let remaining = defaults.integer(forKey: "notif_remaining_\(id)")
                        if remaining > 0 { notification["remainingCount"] = remaining }
                        regularNotifications.append(notification)
                    }
                }

                for (parentId, group) in dayGroups {
                    var notification: [String: Any] = [
                        "id": parentId, "title": group.request.content.title,
                        "body": group.request.content.body, "repeats": true,
                        "repeatDays": group.days.sorted()
                    ]
                    if let trigger = group.request.trigger as? UNCalendarNotificationTrigger,
                       let nextDate = trigger.nextTriggerDate() {
                        notification["nextTriggerAt"] = Int(nextDate.timeIntervalSince1970)
                    }
                    let firstSubId = "\(parentId)_day_\(group.days.sorted().first ?? 1)"
                    let remaining = defaults.integer(forKey: "notif_remaining_\(firstSubId)")
                    if remaining > 0 { notification["remainingCount"] = remaining }
                    regularNotifications.append(notification)
                }

                do {
                    let jsonData = try JSONSerialization.data(withJSONObject: regularNotifications, options: [])
                    let jsonString = String(data: jsonData, encoding: .utf8) ?? "[]"
                    result = ["success": true, "notifications": jsonString, "count": regularNotifications.count]
                } catch {
                    result = ["success": false, "error": error.localizedDescription]
                }
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }

    // MARK: - Update

    class Update: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            guard let id = parameters["id"] as? String else {
                return ["success": false, "error": "Missing required parameter: id"]
            }

            let config = parameters["_config"] as? [String: Any]
            let defaultSound = config?["default_sound"] as? Bool ?? true
            let maxActions = max(1, (config?["max_actions"] as? Int) ?? 3)
            let center = UNUserNotificationCenter.current()

            // Look up existing notification(s)
            let semaphoreFind = DispatchSemaphore(value: 0)
            var allRequests: [UNNotificationRequest] = []

            center.getPendingNotificationRequests { requests in
                allRequests = requests
                semaphoreFind.signal()
            }
            semaphoreFind.wait()

            let daySubRequests = allRequests.filter { $0.identifier.hasPrefix("\(id)_day_") }
            let directRequest = allRequests.first { $0.identifier == id }
            let isDayOfWeek = !daySubRequests.isEmpty

            guard directRequest != nil || isDayOfWeek else {
                return ["success": false, "error": "Notification not found: \(id)"]
            }

            let baseRequest = isDayOfWeek ? daySubRequests.first! : directRequest!
            let existingContent = baseRequest.content

            // Merge parameters with existing content
            let title = parameters["title"] as? String ?? existingContent.title
            let body = parameters["body"] as? String ?? existingContent.body
            let existingSound = existingContent.sound != nil
            let sound = parameters["sound"] as? Bool ?? existingSound
            let badge = parameters["badge"] as? Int ?? existingContent.badge?.intValue
            let subtitle = parameters["subtitle"] as? String
                ?? (existingContent.subtitle.isEmpty ? nil : existingContent.subtitle)

            // Merge data
            var mergedData: [String: Any]?
            if let newData = parameters["data"] as? [String: Any] {
                mergedData = newData
            } else {
                let existing = NotificationHelper.extractCustomData(from: existingContent.userInfo)
                if !existing.isEmpty { mergedData = existing }
            }

            let newContent = NotificationHelper.buildContent(
                id: id, title: title, body: body,
                subtitle: subtitle, sound: sound, badge: badge, data: mergedData
            )

            // Actions
            if let actionsArray = parameters["actions"] as? [[String: Any]], !actionsArray.isEmpty {
                NotificationHelper.registerActions(id: id, actionsArray: actionsArray, maxActions: maxActions, content: newContent)
            } else if !existingContent.categoryIdentifier.isEmpty {
                newContent.categoryIdentifier = existingContent.categoryIdentifier
            }

            // Image
            if let imageUrl = parameters["image"] as? String {
                NotificationHelper.attachImage(to: newContent, imageUrl: imageUrl)
            } else if !existingContent.attachments.isEmpty {
                newContent.attachments = existingContent.attachments
            }

            // Timing
            let newDelay = parameters["delay"] as? Int
            let newAt = parameters["at"] as? Int
            let newRepeat = parameters["repeat"] as? String
            let newRepeatIntervalSeconds = parameters["repeatIntervalSeconds"] as? Int
            let newRepeatDays = parameters["repeatDays"] as? [Int]
            let newRepeatCount = parameters["repeatCount"] as? Int
            let timingChanged = newDelay != nil || newAt != nil || newRepeat != nil
                || newRepeatIntervalSeconds != nil

            // Cannot convert a single notification to day-of-week via update
            if newRepeatDays != nil && !isDayOfWeek {
                return [
                    "success": false,
                    "error": "Cannot add repeatDays to a non-day-of-week notification. Cancel and recreate it instead."
                ]
            }

            if isDayOfWeek {
                let dayTimingChanged = timingChanged || newRepeatDays != nil

                // Remove all existing sub-requests
                let subIds = daySubRequests.map { $0.identifier }
                center.removePendingNotificationRequests(withIdentifiers: subIds)
                center.removeDeliveredNotifications(withIdentifiers: subIds)
                for subId in subIds {
                    UserDefaults.standard.removeObject(forKey: "notif_remaining_\(subId)")
                }

                if dayTimingChanged {
                    // Re-delegate to Schedule with merged params
                    var mergedParams: [String: Any] = [
                        "id": id, "title": title, "body": body, "sound": sound
                    ]
                    if let b = badge { mergedParams["badge"] = b }
                    if let s = subtitle { mergedParams["subtitle"] = s }
                    if let d = mergedData { mergedParams["data"] = d }
                    if let img = parameters["image"] as? String { mergedParams["image"] = img }
                    if let acts = parameters["actions"] { mergedParams["actions"] = acts }
                    if let d = newDelay { mergedParams["delay"] = d }
                    if let a = newAt { mergedParams["at"] = a }
                    if let r = newRepeat { mergedParams["repeat"] = r }
                    if let rs = newRepeatIntervalSeconds { mergedParams["repeatIntervalSeconds"] = rs }
                    if let rd = newRepeatDays { mergedParams["repeatDays"] = rd }
                    if let rc = newRepeatCount { mergedParams["repeatCount"] = rc }
                    if let c = config { mergedParams["_config"] = c }

                    let scheduleResult = try Schedule().execute(parameters: mergedParams)
                    if scheduleResult["success"] as? Bool != true { return scheduleResult }
                } else {
                    // Reschedule sub-alarms with new content, same trigger
                    let semReschedule = DispatchSemaphore(value: 0)
                    var lastError: Error?

                    for oldRequest in daySubRequests {
                        let newRequest = UNNotificationRequest(
                            identifier: oldRequest.identifier, content: newContent,
                            trigger: oldRequest.trigger
                        )
                        center.add(newRequest) { error in
                            if let error = error { lastError = error }
                            semReschedule.signal()
                        }
                        semReschedule.wait()
                    }

                    if let error = lastError {
                        return ["success": false, "error": error.localizedDescription]
                    }

                    if let count = newRepeatCount, count >= 1 {
                        for oldRequest in daySubRequests {
                            UserDefaults.standard.set(count, forKey: "notif_remaining_\(oldRequest.identifier)")
                        }
                    }
                }
            } else {
                // Single notification update
                let requestId = baseRequest.identifier
                center.removePendingNotificationRequests(withIdentifiers: [requestId])
                center.removeDeliveredNotifications(withIdentifiers: [requestId])

                let trigger: UNNotificationTrigger = timingChanged
                    ? NotificationHelper.buildTrigger(
                        delay: newDelay, at: newAt,
                        repeatInterval: newRepeat, repeatIntervalSeconds: newRepeatIntervalSeconds
                    )
                    : baseRequest.trigger ?? UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)

                let newRequest = UNNotificationRequest(identifier: id, content: newContent, trigger: trigger)
                let semAdd = DispatchSemaphore(value: 0)
                var addError: Error?

                center.add(newRequest) { error in
                    addError = error
                    semAdd.signal()
                }
                semAdd.wait()

                if let error = addError {
                    return ["success": false, "error": error.localizedDescription]
                }

                if let count = newRepeatCount, count >= 1 {
                    UserDefaults.standard.set(count, forKey: "notif_remaining_\(id)")
                }
                if requestId != id {
                    UserDefaults.standard.removeObject(forKey: "notif_remaining_\(requestId)")
                }
            }

            let eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationUpdated"
            LaravelBridge.shared.send?(eventClass, ["id": id, "title": title, "body": body])
            return ["success": true, "id": id]
        }
    }

    // MARK: - RequestPermission

    class RequestPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
                if let error = error {
                    print("❌ Permission request error: \(error.localizedDescription)")
                    result = ["granted": false, "error": error.localizedDescription]
                    LaravelBridge.shared.send?("Ikromjon\\LocalNotifications\\Events\\PermissionDenied", [:])
                } else if granted {
                    print("✅ Notification permission granted")
                    result = ["granted": true]
                    LaravelBridge.shared.send?("Ikromjon\\LocalNotifications\\Events\\PermissionGranted", [:])
                } else {
                    print("❌ Notification permission denied")
                    result = ["granted": false]
                    LaravelBridge.shared.send?("Ikromjon\\LocalNotifications\\Events\\PermissionDenied", [:])
                }
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }

    // MARK: - CheckPermission

    class CheckPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            initBridgeCall()

            let center = UNUserNotificationCenter.current()
            let semaphore = DispatchSemaphore(value: 0)
            var result: [String: Any] = [:]

            center.getNotificationSettings { settings in
                let status: String
                switch settings.authorizationStatus {
                case .authorized: status = "granted"
                case .denied: status = "denied"
                case .notDetermined: status = "notDetermined"
                case .provisional: status = "provisional"
                case .ephemeral: status = "ephemeral"
                @unknown default: status = "unknown"
                }
                result = ["status": status]
                semaphore.signal()
            }

            semaphore.wait()
            return result
        }
    }
}
