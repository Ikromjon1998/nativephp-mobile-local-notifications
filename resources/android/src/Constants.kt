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
    const val PERMISSION_DENIED = "Ikromjon\\LocalNotifications\\Events\\PermissionDenied"
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
    const val PRIORITY = "priority"
    const val SILENT = "silent"
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

/** Priority level strings matching the PHP NotificationPriority enum. */
object PriorityLevel {
    const val LOW = "low"
    const val DEFAULT = "default"
    const val HIGH = "high"
    const val URGENT = "urgent"

    val ALL = setOf(LOW, DEFAULT, HIGH, URGENT)

    /**
     * Returns the value when it is a known level, null otherwise, so callers
     * bypassing PHP validation (e.g. the JS bridge) fall back to the legacy
     * no-priority behavior instead of creating junk channels.
     */
    fun normalize(value: String?): String? = value?.takeIf { it in ALL }
}

/**
 * Sub-ID helpers for snooze side-alarms ({id}_snooze). A snooze must be a
 * separate one-shot alarm: reusing the original ID would overwrite a repeating
 * notification's PendingIntent and kill its repeat chain.
 */
object SnoozeId {
    const val SUFFIX = "_snooze"

    /** Sub-ID for the snoozed delivery of a notification; idempotent for
     *  re-snoozes. Always derived from the developer-facing ID, so snoozing a
     *  day-of-week sub-delivery ({id}_day_N) attaches the snooze to the parent
     *  — matching iOS, where the snooze ID comes from the original userInfo ID. */
    fun forId(id: String): String = PublicId.of(id) + SUFFIX

    fun isSnooze(id: String): Boolean = id.endsWith(SUFFIX)

    /** ID with any snooze suffix removed. */
    fun strip(id: String): String = id.removeSuffix(SUFFIX)
}

/** Sub-ID helpers for day-of-week sub-alarms ({id}_day_N). */
object DayOfWeekId {
    const val SEPARATOR = "_day_"
    private val SUFFIX_REGEX = Regex("$SEPARATOR[1-7]$")

    /** ID with any day-of-week suffix removed. */
    fun strip(id: String): String = SUFFIX_REGEX.replace(id, "")
}

/**
 * Maps any internal sub-ID (snooze side-alarm or day-of-week sub-alarm) back
 * to the ID the developer scheduled. Event payloads and getPending() must
 * never expose internal sub-IDs.
 */
object PublicId {
    fun of(id: String): String = DayOfWeekId.strip(SnoozeId.strip(id))
}

/** Default values shared across the plugin. */
object Defaults {
    const val CHANNEL_ID = "nativephp_local_notifications"
}
