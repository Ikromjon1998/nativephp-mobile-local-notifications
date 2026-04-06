package com.nativephp.localnotifications

/** Centralized SharedPreferences key management and thread-safety lock. */
object PrefsKeys {

    /** Single lock for all SharedPreferences read-modify-write operations. */
    val lock = Any()

    // Set keys
    const val NOTIFICATION_IDS = "notification_ids"
    const val REPEAT_DAYS_PARENT_IDS = "repeat_days_parent_ids"
    const val PENDING_EVENTS = "pending_events"

    // Prefix for filtering
    const val TAP_PAYLOAD_PREFIX = "tap_payload_"

    // Per-ID key builders
    fun notificationInfo(id: String) = "notification_$id"
    fun tapPayload(id: String) = "tap_payload_$id"
    fun repeatDays(parentId: String) = "repeat_days_$parentId"
}
