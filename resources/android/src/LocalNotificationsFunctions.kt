package com.nativephp.localnotifications

import android.Manifest
import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.app.Activity
import android.app.Application
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import com.nativephp.mobile.utils.WebViewProvider
import org.json.JSONArray
import org.json.JSONObject
import java.lang.ref.WeakReference

/**
 * Bridge functions for local notification operations.
 * Each inner class implements [BridgeFunction] and is registered in nativephp.json.
 *
 * Scheduling logic and SharedPreferences persistence are delegated to [NotificationScheduler]
 * to avoid duplication between Schedule and Update.
 */
object LocalNotificationsFunctions {

    private const val TAG = "LocalNotifications"
    const val PREFS_NAME = "nativephp_local_notifications_prefs"
    private const val PENDING_EVENTS_KEY = "pending_events"
    private val prefsLock = Any()
    private var resumeCallbackRegistered = false

    // Defaults — overridden at runtime from PHP config via _config parameter
    private var channelId = "nativephp_local_notifications"
    private var channelName = "Local Notifications"
    private var channelDescription = "Notifications scheduled by the app"
    var maxActions = 3
        private set
    private var defaultSound = true
    private var tapDetectionDelayMs = 500L
    private var navigationReplayDurationMs = 15000L

    /** Apply runtime configuration sent from the PHP layer. */
    fun applyConfig(config: Map<*, *>) {
        (config["channel_id"] as? String)?.let { channelId = it }
        (config["channel_name"] as? String)?.let { channelName = it }
        (config["channel_description"] as? String)?.let { channelDescription = it }
        (config["max_actions"] as? Number)?.let { maxActions = maxOf(1, it.toInt()) }
        (config["default_sound"] as? Boolean)?.let { defaultSound = it }
        (config["tap_detection_delay_ms"] as? Number)?.let { tapDetectionDelayMs = it.toLong() }
        (config["navigation_replay_duration_ms"] as? Number)?.let { navigationReplayDurationMs = it.toLong() }
    }

    /** Holds a weak reference to the current activity for event dispatch. */
    object ActivityHolder {
        private var ref: WeakReference<FragmentActivity>? = null
        fun set(activity: FragmentActivity) { ref = WeakReference(activity) }
        fun get(): FragmentActivity? = ref?.get()
    }

    // -----------------------------------------------------------------------
    // Common bridge function setup — called at the start of every execute()
    // -----------------------------------------------------------------------

    /** Apply config, store activity ref, and flush pending events. */
    private fun initBridgeCall(activity: FragmentActivity, parameters: Map<String, Any>) {
        (parameters["_config"] as? Map<*, *>)?.let { applyConfig(it) }
        ActivityHolder.set(activity)
        dispatchPendingEvents(activity)
    }

    // -----------------------------------------------------------------------
    // Notification Channel
    // -----------------------------------------------------------------------

    fun ensureNotificationChannel(context: Context) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channel = NotificationChannel(
            channelId, channelName, NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = channelDescription
            enableVibration(true)
        }
        manager.createNotificationChannel(channel)
    }

    // -----------------------------------------------------------------------
    // Event Dispatch
    // -----------------------------------------------------------------------

    fun dispatchEvent(activity: FragmentActivity, event: String, payloadJson: String) {
        try {
            activity.runOnUiThread {
                try {
                    NativeActionCoordinator.dispatchEvent(activity, event, payloadJson)
                    injectLivewireInitFallback(activity, event, payloadJson)
                } catch (e: Exception) {
                    Log.e(TAG, "Error dispatching event on UI thread: ${e.message}", e)
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error dispatching event: ${e.message}", e)
        }
    }

    private fun injectLivewireInitFallback(activity: FragmentActivity, event: String, payloadJson: String) {
        try {
            val webView = (activity as? WebViewProvider)?.getWebView() ?: return
            val eventForJs = event.replace("\\", "\\\\")
            val js = """
                (function() {
                    if (window.Livewire && typeof window.Livewire.dispatch === 'function') return;
                    document.addEventListener('livewire:init', function() {
                        window.Livewire.dispatch('native:$eventForJs', $payloadJson);
                    }, { once: true });
                })();
            """.trimIndent()
            webView.evaluateJavascript(js, null)
        } catch (e: Exception) {
            Log.e(TAG, "Error injecting livewire:init fallback: ${e.message}", e)
        }
    }

    // -----------------------------------------------------------------------
    // Tap Detection & Pending Events
    // -----------------------------------------------------------------------

    fun storeTapPayload(context: Context, id: String, title: String, body: String, dataJson: String?) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val payload = JSONObject().apply {
                put("id", id)
                put("title", title)
                put("body", body)
                if (dataJson != null) put("data", JSONObject(dataJson))
            }
            prefs.edit().putString("tap_payload_$id", payload.toString()).apply()
            Log.d(TAG, "Stored tap payload for: $id")
        }
    }

    fun clearTapPayload(context: Context, id: String) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            prefs.edit().remove("tap_payload_$id").apply()
            Log.d(TAG, "Cleared tap payload for dismissed notification: $id")
        }
    }

    fun storePendingEvent(context: Context, eventClass: String, payload: JSONObject) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val existing = prefs.getString(PENDING_EVENTS_KEY, null)
            val queue = if (existing != null) JSONArray(existing) else JSONArray()
            queue.put(JSONObject().apply {
                put("event", eventClass)
                put("payload", payload)
            })
            prefs.edit().putString(PENDING_EVENTS_KEY, queue.toString()).apply()
            Log.d(TAG, "Stored pending event ($eventClass), queue size: ${queue.length()}")
        }
    }

    private fun registerResumeDetection(activity: FragmentActivity) {
        if (resumeCallbackRegistered) return
        resumeCallbackRegistered = true

        activity.application.registerActivityLifecycleCallbacks(object : Application.ActivityLifecycleCallbacks {
            override fun onActivityResumed(a: Activity) {
                if (a is FragmentActivity) {
                    ActivityHolder.set(a)
                    Handler(Looper.getMainLooper()).postDelayed({
                        detectTappedNotifications(a)
                    }, tapDetectionDelayMs)
                }
            }
            override fun onActivityCreated(a: Activity, b: Bundle?) {}
            override fun onActivityStarted(a: Activity) {}
            override fun onActivityPaused(a: Activity) {}
            override fun onActivityStopped(a: Activity) {}
            override fun onActivitySaveInstanceState(a: Activity, b: Bundle) {}
            override fun onActivityDestroyed(a: Activity) {}
        })
        Log.d(TAG, "Registered onResume callback for tap detection")
    }

    private fun injectNavigationReplay(activity: FragmentActivity, event: String, payloadJson: String) {
        try {
            val webView = (activity as? WebViewProvider)?.getWebView() ?: return
            val eventForJs = event.replace("\\", "\\\\")
            val js = """
                (function() {
                    var expiry = Date.now() + $navigationReplayDurationMs;
                    function handler() {
                        if (Date.now() > expiry) {
                            document.removeEventListener('livewire:navigated', handler);
                            return;
                        }
                        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                            window.Livewire.dispatch('native:$eventForJs', $payloadJson);
                        }
                    }
                    document.addEventListener('livewire:navigated', handler);
                })();
            """.trimIndent()
            activity.runOnUiThread { webView.evaluateJavascript(js, null) }
        } catch (e: Exception) {
            Log.e(TAG, "Error injecting navigation replay: ${e.message}", e)
        }
    }

    private fun dispatchPendingEvents(activity: FragmentActivity) {
        registerResumeDetection(activity)

        // Cold-start tap from PendingIntent
        val intent = activity.intent
        if (intent?.action == "com.nativephp.localnotifications.TAP") {
            val id = intent.getStringExtra("notification_id")
            val title = intent.getStringExtra("notification_title")
            val body = intent.getStringExtra("notification_body")
            val dataJson = intent.getStringExtra("notification_data")

            if (id != null && title != null && body != null) {
                val payload = JSONObject().apply {
                    put("id", id)
                    put("title", title)
                    put("body", body)
                    if (dataJson != null) put("data", JSONObject(dataJson))
                }
                val payloadStr = payload.toString()
                val eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
                Log.d(TAG, "Dispatching NotificationTapped from activity intent: $id")
                dispatchEvent(activity, eventClass, payloadStr)
                injectNavigationReplay(activity, eventClass, payloadStr)
                clearTapPayload(activity, id)
            }
            intent.action = null
        }

        // Warm-start tap detection
        detectTappedNotifications(activity)

        // Flush queued events
        val prefs = activity.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val eventsJson = prefs.getString(PENDING_EVENTS_KEY, null) ?: return
        prefs.edit().remove(PENDING_EVENTS_KEY).apply()

        val queue = JSONArray(eventsJson)
        Log.d(TAG, "Dispatching ${queue.length()} pending event(s) from queue")
        for (i in 0 until queue.length()) {
            val entry = queue.getJSONObject(i)
            dispatchEvent(activity, entry.getString("event"), entry.getJSONObject("payload").toString())
        }
    }

    private fun detectTappedNotifications(activity: FragmentActivity) {
        val prefs = activity.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val allKeys = prefs.all.keys.filter { it.startsWith("tap_payload_") }
        if (allKeys.isEmpty()) return

        val notificationManager = activity.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val activeIds = notificationManager.activeNotifications.map { it.id }.toSet()

        for (key in allKeys) {
            val notifId = key.removePrefix("tap_payload_")
            if (notifId.hashCode() !in activeIds) {
                val payloadJson = prefs.getString(key, null) ?: continue
                val eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
                Log.d(TAG, "Detected tapped notification (warm-start): $notifId")
                dispatchEvent(activity, eventClass, payloadJson)
                injectNavigationReplay(activity, eventClass, payloadJson)
                clearTapPayload(activity, notifId)
            }
        }
    }

    // =======================================================================
    // Bridge Functions
    // =======================================================================

    /** Schedule a local notification. */
    class Schedule(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")
            if ((parameters["title"] as? String) == null)
                return mapOf("success" to false, "error" to "Missing required parameter: title")
            if ((parameters["body"] as? String) == null)
                return mapOf("success" to false, "error" to "Missing required parameter: body")

            val params = NotificationScheduler.parseParams(parameters, defaultSound)
            val delay = (parameters["delay"] as? Number)?.toLong()
            val atTimestamp = (parameters["at"] as? Number)?.toLong()
            val repeatInterval = parameters["repeat"] as? String
            val repeatIntervalSeconds = (parameters["repeatIntervalSeconds"] as? Number)?.toLong()
            val repeatDaysList = NotificationScheduler.parseRepeatDays(parameters)
            val repeatCount = (parameters["repeatCount"] as? Number)?.toInt()

            val context = activity as Context
            ensureNotificationChannel(context)

            return try {
                if (repeatDaysList != null && repeatDaysList.isNotEmpty() && atTimestamp != null) {
                    // Day-of-week scheduling
                    val subIds = NotificationScheduler.scheduleDayOfWeekAlarms(
                        context, id, params, atTimestamp, repeatDaysList, repeatCount, channelId
                    )
                    NotificationScheduler.saveRepeatDaysParent(context, id, subIds)

                    Log.d(TAG, "✅ Day-of-week notification scheduled: $id (${subIds.size} sub-alarms)")
                    NotificationScheduler.dispatchNotificationEvent(
                        activity, "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled",
                        id, params.title, params.body
                    )
                    mapOf("success" to true, "id" to id)
                } else {
                    // Standard single-alarm scheduling
                    val triggerTimeMs = NotificationScheduler.calculateTriggerTimeMs(delay, atTimestamp)
                    val repeatMs = NotificationScheduler.calculateRepeatMs(repeatInterval, repeatIntervalSeconds)

                    NotificationScheduler.scheduleAlarm(context, id, params, triggerTimeMs, repeatMs, repeatInterval, repeatCount, channelId)
                    NotificationScheduler.saveNotificationInfo(context, id, params, triggerTimeMs, repeatMs, repeatInterval, repeatCount, channelId)

                    Log.d(TAG, "✅ Notification scheduled: $id at $triggerTimeMs")
                    NotificationScheduler.dispatchNotificationEvent(
                        activity, "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled",
                        id, params.title, params.body
                    )
                    mapOf("success" to true, "id" to id)
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error scheduling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /** Cancel a scheduled notification by identifier. */
    class Cancel(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")

            val context = activity as Context
            return try {
                val subIds = NotificationScheduler.getRepeatDaysSubIds(context, id)
                if (subIds != null) {
                    for (subId in subIds) {
                        NotificationScheduler.cancelAlarm(context, subId)
                        NotificationScheduler.removeNotificationInfo(context, subId)
                        clearTapPayload(context, subId)
                    }
                    NotificationScheduler.removeRepeatDaysParent(context, id)
                    Log.d(TAG, "✅ Day-of-week notification cancelled: $id (${subIds.size} sub-alarms)")
                } else {
                    NotificationScheduler.cancelAlarm(context, id)
                    NotificationScheduler.removeNotificationInfo(context, id)
                    clearTapPayload(context, id)
                    Log.d(TAG, "✅ Notification cancelled: $id")
                }
                mapOf("success" to true, "id" to id)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error cancelling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /** Cancel all scheduled notifications. */
    class CancelAll(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val context = activity as Context
            return try {
                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

                for (id in allIds) {
                    NotificationScheduler.cancelAlarm(context, id)
                }

                prefs.edit().clear().apply()

                val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                notificationManager.cancelAll()

                Log.d(TAG, "✅ All notifications cancelled")
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error cancelling all notifications: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /** Get all pending scheduled notifications. */
    class GetPending(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val context = activity as Context
            return try {
                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

                val parentIds = prefs.getStringSet("repeat_days_parent_ids", emptySet()) ?: emptySet()
                val subIdSet = mutableSetOf<String>()
                for (parentId in parentIds) {
                    NotificationScheduler.getRepeatDaysSubIds(context, parentId)?.let { subIdSet.addAll(it) }
                }

                val notifications = JSONArray()

                for (id in allIds) {
                    if (id in subIdSet) continue
                    val infoJson = prefs.getString("notification_$id", null) ?: continue
                    notifications.put(JSONObject(infoJson))
                }

                for (parentId in parentIds) {
                    val subIds = NotificationScheduler.getRepeatDaysSubIds(context, parentId) ?: continue
                    val firstSubId = subIds.firstOrNull() ?: continue
                    val firstInfoJson = prefs.getString("notification_$firstSubId", null) ?: continue
                    val parentInfo = JSONObject(firstInfoJson)
                    parentInfo.put("id", parentId)

                    val days = JSONArray()
                    for (subId in subIds) {
                        val dayStr = subId.substringAfterLast("_day_")
                        days.put(dayStr.toIntOrNull() ?: continue)
                    }
                    parentInfo.put("repeatDays", days)
                    notifications.put(parentInfo)
                }

                mapOf("success" to true, "notifications" to notifications.toString(), "count" to notifications.length())
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error getting pending notifications: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /** Request notification permission (Android 13+). */
    class RequestPermission(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    val hasPermission = ContextCompat.checkSelfPermission(
                        activity, Manifest.permission.POST_NOTIFICATIONS
                    ) == PackageManager.PERMISSION_GRANTED

                    if (hasPermission) {
                        Log.d(TAG, "✅ Notification permission already granted")
                        dispatchEvent(activity, "Ikromjon\\LocalNotifications\\Events\\PermissionGranted", "{}")
                        return mapOf("granted" to true)
                    }

                    activity.requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 1001)
                    mapOf("granted" to false, "status" to "pending")
                } else {
                    Log.d(TAG, "✅ Notification permission granted (pre-Android 13)")
                    dispatchEvent(activity, "Ikromjon\\LocalNotifications\\Events\\PermissionGranted", "{}")
                    mapOf("granted" to true)
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error requesting permission: ${e.message}", e)
                mapOf("granted" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /** Check current notification permission status. */
    class CheckPermission(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val context = activity as Context
            return try {
                val status = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    val hasPermission = ContextCompat.checkSelfPermission(
                        context, Manifest.permission.POST_NOTIFICATIONS
                    ) == PackageManager.PERMISSION_GRANTED
                    if (hasPermission) "granted" else "denied"
                } else {
                    val mgr = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                    if (mgr.areNotificationsEnabled()) "granted" else "denied"
                }
                mapOf("status" to status)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error checking permission: ${e.message}", e)
                mapOf("status" to "unknown", "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Update an existing scheduled notification.
     * Merges new parameters with stored notification info. Content-only changes
     * preserve the original trigger time; timing changes reschedule the alarm.
     */
    class Update(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            initBridgeCall(activity, parameters)

            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")

            val context = activity as Context
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

            val subIds = NotificationScheduler.getRepeatDaysSubIds(context, id)
            val lookupId = if (subIds != null) subIds.firstOrNull() ?: id else id
            val existingJson = prefs.getString("notification_$lookupId", null)
                ?: return mapOf("success" to false, "error" to "Notification not found: $id")

            return try {
                val existing = JSONObject(existingJson)
                val params = NotificationScheduler.mergeParams(parameters, existing, defaultSound)

                // Timing properties
                val newDelay = (parameters["delay"] as? Number)?.toLong()
                val newAt = (parameters["at"] as? Number)?.toLong()
                val newRepeat = parameters["repeat"] as? String
                val newRepeatIntervalSeconds = (parameters["repeatIntervalSeconds"] as? Number)?.toLong()
                val newRepeatDays = NotificationScheduler.parseRepeatDays(parameters)
                val newRepeatCount = (parameters["repeatCount"] as? Number)?.toInt()

                val timingChanged = newDelay != null || newAt != null || newRepeat != null
                    || newRepeatIntervalSeconds != null

                ensureNotificationChannel(context)

                // Cannot convert a single notification to day-of-week via update
                if (newRepeatDays != null && subIds == null) {
                    return mapOf(
                        "success" to false,
                        "error" to "Cannot add repeatDays to a non-day-of-week notification. Cancel and recreate it instead."
                    )
                }

                val dayTimingChanged = timingChanged || newRepeatDays != null

                if (subIds != null && !dayTimingChanged) {
                    // Content-only update for day-of-week sub-alarms
                    for (subId in subIds) {
                        val subJson = prefs.getString("notification_$subId", null) ?: continue
                        val sub = JSONObject(subJson)
                        val remainingCount = if (sub.has("remainingCount")) sub.optInt("remainingCount") else null

                        NotificationScheduler.cancelAlarm(context, subId)
                        NotificationScheduler.scheduleAlarm(context, subId, params, sub.optLong("triggerTimeMs"), sub.optLong("repeatMs"), sub.optString("repeatType", null), remainingCount, channelId)
                        NotificationScheduler.saveNotificationInfo(context, subId, params, sub.optLong("triggerTimeMs"), sub.optLong("repeatMs"), sub.optString("repeatType", null), remainingCount, channelId)
                    }
                } else if (subIds != null && dayTimingChanged) {
                    // Cancel all sub-alarms and re-delegate to Schedule
                    for (subId in subIds) {
                        NotificationScheduler.cancelAlarm(context, subId)
                        NotificationScheduler.removeNotificationInfo(context, subId)
                        clearTapPayload(context, subId)
                    }
                    NotificationScheduler.removeRepeatDaysParent(context, id)

                    val mergedParams = buildMergedScheduleParams(id, params, parameters, newDelay, newAt, newRepeat, newRepeatIntervalSeconds, newRepeatDays, newRepeatCount)
                    val scheduleResult = Schedule(activity).execute(mergedParams)
                    if (scheduleResult["success"] != true) return scheduleResult
                } else {
                    // Single notification update
                    val existingRemainingCount = if (existing.has("remainingCount")) existing.optInt("remainingCount") else null

                    val triggerTimeMs: Long
                    val repeatMs: Long
                    val repeatType: String?
                    val repeatCount: Int?

                    if (timingChanged) {
                        triggerTimeMs = NotificationScheduler.calculateTriggerTimeMs(newDelay, newAt)
                            .let { if (newDelay == null && newAt == null) existing.optLong("triggerTimeMs") else it }
                        val effectiveRepeat = newRepeat ?: existing.optString("repeatType", null)
                        repeatMs = NotificationScheduler.calculateRepeatMs(effectiveRepeat, newRepeatIntervalSeconds)
                        repeatType = effectiveRepeat
                        repeatCount = newRepeatCount ?: existingRemainingCount
                    } else {
                        triggerTimeMs = existing.optLong("triggerTimeMs")
                        repeatMs = existing.optLong("repeatMs")
                        repeatType = existing.optString("repeatType", null)
                        repeatCount = newRepeatCount ?: existingRemainingCount
                    }

                    NotificationScheduler.cancelAlarm(context, id)
                    NotificationScheduler.scheduleAlarm(context, id, params, triggerTimeMs, repeatMs, repeatType, repeatCount, channelId)
                    NotificationScheduler.saveNotificationInfo(context, id, params, triggerTimeMs, repeatMs, repeatType, repeatCount, channelId)

                    // Refresh already-delivered notification if visible
                    refreshDeliveredNotification(context, id, params)
                }

                Log.d(TAG, "✅ Notification updated: $id")
                NotificationScheduler.dispatchNotificationEvent(
                    activity, "Ikromjon\\LocalNotifications\\Events\\NotificationUpdated",
                    id, params.title, params.body
                )
                mapOf("success" to true, "id" to id)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error updating notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }

        private fun buildMergedScheduleParams(
            id: String, params: NotificationParams, original: Map<String, Any>,
            delay: Long?, at: Long?, repeat: String?, repeatIntervalSeconds: Long?,
            repeatDays: List<Int>?, repeatCount: Int?
        ): Map<String, Any> {
            val merged = mutableMapOf<String, Any>("id" to id, "title" to params.title, "body" to params.body, "sound" to params.sound)
            if (params.badge != null) merged["badge"] = params.badge
            if (params.data != null) merged["data"] = params.data
            if (params.subtitle != null) merged["subtitle"] = params.subtitle
            if (params.imageUrl != null) merged["image"] = params.imageUrl
            if (params.bigText != null) merged["bigText"] = params.bigText
            if (params.actions != null) merged["actions"] = params.actions
            if (delay != null) merged["delay"] = delay
            if (at != null) merged["at"] = at
            if (repeat != null) merged["repeat"] = repeat
            if (repeatIntervalSeconds != null) merged["repeatIntervalSeconds"] = repeatIntervalSeconds
            if (repeatDays != null) merged["repeatDays"] = repeatDays
            if (repeatCount != null) merged["repeatCount"] = repeatCount
            (original["_config"] as? Map<*, *>)?.let { merged["_config"] = it }
            return merged
        }

        private fun refreshDeliveredNotification(context: Context, id: String, params: NotificationParams) {
            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val activeNotification = notificationManager.activeNotifications.firstOrNull { it.id == id.hashCode() } ?: return

            val rebuiltNotification = android.app.Notification.Builder(context, channelId)
                .setSmallIcon(activeNotification.notification.smallIcon
                    ?: android.graphics.drawable.Icon.createWithResource(context, context.applicationInfo.icon))
                .setContentTitle(params.title)
                .setContentText(params.body)
                .apply {
                    if (params.subtitle != null) setSubText(params.subtitle)
                    if (!params.bigText.isNullOrBlank()) {
                        setStyle(android.app.Notification.BigTextStyle().bigText(params.bigText))
                    }
                    setAutoCancel(true)
                }
                .build()

            notificationManager.notify(id.hashCode(), rebuiltNotification)
            Log.d(TAG, "Refreshed delivered notification: $id")
        }
    }
}
