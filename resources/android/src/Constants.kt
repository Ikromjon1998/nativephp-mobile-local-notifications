package com.nativephp.localnotifications

/** Intent action constants used across receivers and bridge functions. */
object IntentActions {
    const val TAP = "com.nativephp.localnotifications.TAP"
    const val NOTIFY = "com.nativephp.localnotifications.NOTIFY"
    const val DISMISS = "com.nativephp.localnotifications.DISMISS"
    const val ACTION = "com.nativephp.localnotifications.ACTION"
}

/** Laravel event fully-qualified class names dispatched to the PHP layer. */
object Events {
    const val NOTIFICATION_TAPPED = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
    const val NOTIFICATION_RECEIVED = "Ikromjon\\LocalNotifications\\Events\\NotificationReceived"
    const val NOTIFICATION_SCHEDULED = "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled"
    const val NOTIFICATION_UPDATED = "Ikromjon\\LocalNotifications\\Events\\NotificationUpdated"
    const val NOTIFICATION_ACTION_PRESSED = "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed"
    const val PERMISSION_GRANTED = "Ikromjon\\LocalNotifications\\Events\\PermissionGranted"
}
