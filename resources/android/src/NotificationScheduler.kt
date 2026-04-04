package com.nativephp.localnotifications

import android.app.AlarmManager
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.util.Log
import org.json.JSONArray
import org.json.JSONObject
import java.util.Calendar

/**
 * Parsed notification parameters. Used by Schedule and Update to avoid
 * passing 14+ individual arguments through every call.
 */
data class NotificationParams(
    val id: String,
    val title: String,
    val body: String,
    val sound: Boolean,
    val badge: Int?,
    val data: Map<*, *>?,
    val subtitle: String?,
    val imageUrl: String?,
    val bigText: String?,
    val actions: List<*>?,
)

/**
 * Shared scheduling utilities used by Schedule, Update, Cancel, and BootReceiver.
 * Eliminates duplicated timing calculations, alarm management, and event dispatch
 * that was previously copy-pasted across bridge function classes.
 */
object NotificationScheduler {

    private const val TAG = "LocalNotifications"

    // -----------------------------------------------------------------------
    // Parameter Parsing
    // -----------------------------------------------------------------------

    /**
     * Parse notification parameters from a bridge call's parameter map.
     * Used by Schedule for fresh notifications.
     */
    fun parseParams(parameters: Map<String, Any>, defaultSound: Boolean): NotificationParams {
        return NotificationParams(
            id = parameters["id"] as? String ?: "",
            title = parameters["title"] as? String ?: "",
            body = parameters["body"] as? String ?: "",
            sound = parameters["sound"] as? Boolean ?: defaultSound,
            badge = (parameters["badge"] as? Number)?.toInt(),
            data = parameters["data"] as? Map<*, *>,
            subtitle = parameters["subtitle"] as? String,
            imageUrl = parameters["image"] as? String,
            bigText = parameters["bigText"] as? String,
            actions = parameters["actions"] as? List<*>,
        )
    }

    /**
     * Parse notification parameters from a bridge call, merging with existing stored
     * notification data. New values override existing; missing values fall back to stored.
     * Used by Update when modifying an existing notification.
     */
    fun mergeParams(
        parameters: Map<String, Any>,
        existing: JSONObject,
        defaultSound: Boolean
    ): NotificationParams {
        return NotificationParams(
            id = parameters["id"] as? String ?: existing.optString("id"),
            title = parameters["title"] as? String ?: existing.optString("title"),
            body = parameters["body"] as? String ?: existing.optString("body"),
            sound = parameters["sound"] as? Boolean ?: existing.optBoolean("sound", defaultSound),
            badge = (parameters["badge"] as? Number)?.toInt()
                ?: if (existing.has("badge")) existing.optInt("badge") else null,
            data = parameters["data"] as? Map<*, *>
                ?: if (existing.has("data")) jsonObjectToMap(existing.getJSONObject("data")) else null,
            subtitle = parameters["subtitle"] as? String
                ?: existing.optString("subtitle", null),
            imageUrl = parameters["image"] as? String
                ?: existing.optString("image", null),
            bigText = parameters["bigText"] as? String
                ?: existing.optString("bigText", null),
            actions = parameters["actions"] as? List<*>
                ?: if (existing.has("actions")) jsonArrayToActionList(existing.getJSONArray("actions")) else null,
        )
    }

    // -----------------------------------------------------------------------
    // Timing Calculations
    // -----------------------------------------------------------------------

    /**
     * Calculate the absolute trigger time in milliseconds.
     */
    fun calculateTriggerTimeMs(delay: Long?, atTimestamp: Long?): Long {
        return when {
            delay != null && delay > 0 -> System.currentTimeMillis() + (delay * 1000)
            atTimestamp != null -> atTimestamp * 1000
            else -> System.currentTimeMillis() + 1000
        }
    }

    /**
     * Calculate the repeat interval in milliseconds from either a named interval
     * or a custom seconds value.
     *
     * Special return values: -1L = monthly, -2L = yearly (calendar-based),
     * 0L = no repeat.
     */
    fun calculateRepeatMs(repeatInterval: String?, repeatIntervalSeconds: Long?): Long {
        if (repeatIntervalSeconds != null && repeatIntervalSeconds >= 60 && repeatInterval == null) {
            return repeatIntervalSeconds * 1000L
        }
        return when (repeatInterval) {
            "minute" -> 60_000L
            "hourly" -> 3_600_000L
            "daily" -> AlarmManager.INTERVAL_DAY
            "weekly" -> AlarmManager.INTERVAL_DAY * 7
            "monthly" -> -1L
            "yearly" -> -2L
            else -> 0L
        }
    }

    /**
     * Calculate the next trigger time for calendar-based repeat types (monthly/yearly).
     */
    fun calculateNextTrigger(repeatType: String, currentTriggerMs: Long): Long {
        val cal = Calendar.getInstance().apply { timeInMillis = currentTriggerMs }
        when (repeatType) {
            "monthly" -> cal.add(Calendar.MONTH, 1)
            "yearly" -> cal.add(Calendar.YEAR, 1)
        }
        return cal.timeInMillis
    }

    // -----------------------------------------------------------------------
    // Day-of-Week Scheduling
    // -----------------------------------------------------------------------

    /**
     * Parse repeatDays from bridge parameters, filtering to valid ISO days (1-7).
     */
    fun parseRepeatDays(parameters: Map<String, Any>): List<Int>? {
        return (parameters["repeatDays"] as? List<*>)?.mapNotNull {
            (it as? Number)?.toInt()
        }?.filter { it in 1..7 }
    }

    /**
     * Schedule day-of-week sub-alarms. Creates one alarm per ISO day, each firing
     * weekly at the time derived from [atTimestamp].
     *
     * @return list of sub-IDs created (e.g. "habit_day_1", "habit_day_3")
     */
    fun scheduleDayOfWeekAlarms(
        context: Context,
        id: String,
        params: NotificationParams,
        atTimestamp: Long,
        days: List<Int>,
        repeatCount: Int?,
        channelId: String
    ): List<String> {
        val baseDate = Calendar.getInstance().apply {
            timeInMillis = atTimestamp * 1000
        }
        val subIds = mutableListOf<String>()
        val weekMs = AlarmManager.INTERVAL_DAY * 7

        for (isoDay in days) {
            val subId = "${id}_day_$isoDay"
            subIds.add(subId)

            // Convert ISO day (1=Mon..7=Sun) to Java Calendar day (1=Sun..7=Sat)
            val calDay = if (isoDay == 7) Calendar.SUNDAY else isoDay + 1

            val triggerCal = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, baseDate.get(Calendar.HOUR_OF_DAY))
                set(Calendar.MINUTE, baseDate.get(Calendar.MINUTE))
                set(Calendar.SECOND, baseDate.get(Calendar.SECOND))
                set(Calendar.MILLISECOND, 0)
                set(Calendar.DAY_OF_WEEK, calDay)
                if (timeInMillis <= System.currentTimeMillis()) {
                    add(Calendar.WEEK_OF_YEAR, 1)
                }
            }

            val triggerTimeMs = triggerCal.timeInMillis
            scheduleAlarm(context, subId, params, triggerTimeMs, weekMs, "weekly", repeatCount, channelId)
            saveNotificationInfo(context, subId, params, triggerTimeMs, weekMs, "weekly", repeatCount, channelId)
        }

        return subIds
    }

    // -----------------------------------------------------------------------
    // Alarm Management
    // -----------------------------------------------------------------------

    /**
     * Schedule a single alarm via AlarmManager.
     */
    fun scheduleAlarm(
        context: Context,
        id: String,
        params: NotificationParams,
        triggerTimeMs: Long,
        repeatMs: Long,
        repeatType: String?,
        repeatCount: Int?,
        channelId: String
    ) {
        val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.nativephp.localnotifications.NOTIFY"
            putExtra("notification_id", id)
            putExtra("title", params.title)
            putExtra("body", params.body)
            putExtra("sound", params.sound)
            putExtra("channel_id", channelId)
            if (params.badge != null) putExtra("badge", params.badge)
            if (repeatMs != 0L) putExtra("repeat_ms", repeatMs)
            if (repeatType != null) putExtra("repeat_type", repeatType)
            if (repeatCount != null) putExtra("remaining_count", repeatCount)
            if (params.data != null) {
                val dataJson = JSONObject(params.data.mapKeys { it.key.toString() }).toString()
                putExtra("data", dataJson)
            }
            if (params.subtitle != null) putExtra("subtitle", params.subtitle)
            if (params.imageUrl != null) putExtra("image", params.imageUrl)
            if (params.bigText != null) putExtra("big_text", params.bigText)
            if (params.actions != null) {
                putExtra("actions", serializeActions(params.actions).toString())
            }
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            id.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerTimeMs, pendingIntent)
    }

    /**
     * Cancel a single alarm and its displayed notification.
     * Shared by Cancel and Update bridge functions.
     */
    fun cancelAlarm(context: Context, id: String) {
        val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.nativephp.localnotifications.NOTIFY"
        }
        val requestCode = id.hashCode()
        val pendingIntent = PendingIntent.getBroadcast(
            context, requestCode, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.cancel(pendingIntent)
        pendingIntent.cancel()

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.cancel(requestCode)
    }

    // -----------------------------------------------------------------------
    // SharedPreferences Persistence
    // -----------------------------------------------------------------------

    /** Lock for thread-safe SharedPreferences access. */
    private val prefsLock = Any()

    /**
     * Save notification info to SharedPreferences for boot recovery and getPending.
     */
    fun saveNotificationInfo(
        context: Context,
        id: String,
        params: NotificationParams,
        triggerTimeMs: Long,
        repeatMs: Long,
        repeatType: String?,
        remainingCount: Int?,
        channelId: String
    ) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.add(id)

            val info = JSONObject().apply {
                put("id", id)
                put("title", params.title)
                put("body", params.body)
                put("triggerTimeMs", triggerTimeMs)
                put("repeatMs", repeatMs)
                put("channelId", channelId)
                if (repeatType != null) put("repeatType", repeatType)
                if (remainingCount != null) put("remainingCount", remainingCount)
                put("sound", params.sound)
                if (params.badge != null) put("badge", params.badge)
                if (params.data != null) put("data", JSONObject(params.data.mapKeys { it.key.toString() }))
                if (params.subtitle != null) put("subtitle", params.subtitle)
                if (params.imageUrl != null) put("image", params.imageUrl)
                if (params.bigText != null) put("bigText", params.bigText)
                if (params.actions != null) put("actions", serializeActions(params.actions))
            }

            prefs.edit()
                .putStringSet("notification_ids", ids)
                .putString("notification_$id", info.toString())
                .apply()
        }
    }

    /**
     * Remove notification info from SharedPreferences.
     */
    fun removeNotificationInfo(context: Context, id: String) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)

            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        }
    }

    /**
     * Save a parent entry that maps a logical notification ID to its day-of-week sub-IDs.
     */
    fun saveRepeatDaysParent(context: Context, parentId: String, subIds: List<String>) {
        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val parentIds = prefs.getStringSet("repeat_days_parent_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        parentIds.add(parentId)

        prefs.edit()
            .putStringSet("repeat_days_parent_ids", parentIds)
            .putString("repeat_days_$parentId", JSONArray(subIds).toString())
            .apply()
    }

    /**
     * Get the sub-IDs for a repeatDays parent, or null if not a parent ID.
     */
    fun getRepeatDaysSubIds(context: Context, parentId: String): List<String>? {
        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val json = prefs.getString("repeat_days_$parentId", null) ?: return null
        val arr = JSONArray(json)
        return (0 until arr.length()).map { arr.getString(it) }
    }

    /**
     * Remove a repeatDays parent entry and its sub-ID mapping.
     */
    fun removeRepeatDaysParent(context: Context, parentId: String) {
        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val parentIds = prefs.getStringSet("repeat_days_parent_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        parentIds.remove(parentId)

        prefs.edit()
            .putStringSet("repeat_days_parent_ids", parentIds)
            .remove("repeat_days_$parentId")
            .apply()
    }

    // -----------------------------------------------------------------------
    // Event Dispatch
    // -----------------------------------------------------------------------

    /**
     * Build and dispatch a notification lifecycle event (Scheduled, Updated, etc.).
     */
    fun dispatchNotificationEvent(
        activity: androidx.fragment.app.FragmentActivity,
        eventClass: String,
        id: String,
        title: String,
        body: String
    ) {
        val payload = JSONObject().apply {
            put("id", id)
            put("title", title)
            put("body", body)
        }
        LocalNotificationsFunctions.dispatchEvent(activity, eventClass, payload.toString())
    }

    // -----------------------------------------------------------------------
    // Serialization Helpers
    // -----------------------------------------------------------------------

    /**
     * Serialize a list of action maps into a JSONArray.
     */
    fun serializeActions(actions: List<*>, maxActions: Int = LocalNotificationsFunctions.maxActions): JSONArray {
        val actionsJson = JSONArray()
        for (action in actions.take(maxActions)) {
            val actionMap = action as? Map<*, *> ?: continue
            actionsJson.put(JSONObject().apply {
                put("id", actionMap["id"]?.toString() ?: "")
                put("title", actionMap["title"]?.toString() ?: "")
                put("destructive", actionMap["destructive"] as? Boolean ?: false)
                put("input", actionMap["input"] as? Boolean ?: false)
            })
        }
        return actionsJson
    }

    private fun jsonObjectToMap(obj: JSONObject): MutableMap<String, Any> {
        val map = mutableMapOf<String, Any>()
        for (key in obj.keys()) { map[key] = obj.get(key) }
        return map
    }

    private fun jsonArrayToActionList(arr: JSONArray): List<Map<String, Any>> {
        return (0 until arr.length()).map { i ->
            val a = arr.getJSONObject(i)
            mapOf(
                "id" to a.optString("id"),
                "title" to a.optString("title"),
                "destructive" to a.optBoolean("destructive", false),
                "input" to a.optBoolean("input", false)
            )
        }
    }
}
