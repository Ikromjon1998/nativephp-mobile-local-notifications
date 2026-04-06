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

/** Intent extra key strings shared across all receivers and schedulers. */
object IntentExtras {
    const val NOTIFICATION_ID = "notification_id"
    const val TITLE = "title"
    const val BODY = "body"
    const val SOUND = "sound"
    const val SOUND_NAME = "sound_name"
    const val CHANNEL_ID = "channel_id"
    const val BADGE = "badge"
    const val DATA = "data"
    const val SUBTITLE = "subtitle"
    const val IMAGE = "image"
    const val BIG_TEXT = "big_text"
    const val ACTIONS = "actions"
    const val REPEAT_MS = "repeat_ms"
    const val REPEAT_TYPE = "repeat_type"
    const val REMAINING_COUNT = "remaining_count"
    const val NOTIFICATION_TITLE = "notification_title"
    const val NOTIFICATION_BODY = "notification_body"
    const val NOTIFICATION_DATA = "notification_data"
    const val ACTION_ID = "action_id"
    const val SNOOZE_SECONDS = "snooze_seconds"
}

/** Repeat interval type strings matching the PHP RepeatInterval enum. */
object RepeatType {
    const val MINUTE = "minute"
    const val HOURLY = "hourly"
    const val DAILY = "daily"
    const val WEEKLY = "weekly"
    const val MONTHLY = "monthly"
    const val YEARLY = "yearly"
}

/** Default values shared across the plugin. */
object Defaults {
    const val CHANNEL_ID = "nativephp_local_notifications"
}
