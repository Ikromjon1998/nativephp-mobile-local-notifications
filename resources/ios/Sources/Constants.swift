import Foundation

/// Laravel event fully-qualified class names dispatched to the PHP layer.
enum Events {
    static let notificationReceived = "Ikromjon\\LocalNotifications\\Events\\NotificationReceived"
    static let notificationTapped = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
    static let notificationScheduled = "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled"
    static let notificationUpdated = "Ikromjon\\LocalNotifications\\Events\\NotificationUpdated"
    static let notificationActionPressed = "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed"
    static let permissionGranted = "Ikromjon\\LocalNotifications\\Events\\PermissionGranted"
    static let permissionDenied = "Ikromjon\\LocalNotifications\\Events\\PermissionDenied"
}

/// Keys used in UNNotificationContent.userInfo dictionaries.
/// These are reserved internal keys that must not collide with user-provided data.
enum UserInfoKeys {
    static let notificationId = "notification_id"
    static let soundName = "soundName"
    static let actionSnooze = "action_snooze"
}

/// Repeat interval type strings matching the PHP RepeatInterval enum.
enum RepeatType {
    static let minute = "minute"
    static let hourly = "hourly"
    static let daily = "daily"
    static let weekly = "weekly"
    static let monthly = "monthly"
    static let yearly = "yearly"
}

/// UserDefaults key helpers and notification sub-ID formatters.
enum NotificationKeys {
    /// UserDefaults key for the remaining repeat count of a notification request.
    static func remainingCount(_ requestId: String) -> String {
        "notif_remaining_\(requestId)"
    }

    /// Build a day-of-week sub-identifier from a parent ID and ISO day number.
    static func daySubId(_ parentId: String, isoDay: Int) -> String {
        "\(parentId)\(daySeparator)\(isoDay)"
    }

    /// Separator used in day-of-week sub-identifiers. Used for both
    /// construction and parsing (e.g., in GetPending).
    static let daySeparator = "_day_"
}
