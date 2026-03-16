package com.ikromjon.localnotifications

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
import java.util.Calendar

/**
 * Functions related to local notification operations
 * Namespace: "LocalNotifications.*"
 */
object LocalNotificationsFunctions {

    private const val TAG = "LocalNotifications"
    const val PREFS_NAME = "nativephp_local_notifications_prefs"
    private const val PENDING_EVENTS_KEY = "pending_events"
    /** Lock object for thread-safe SharedPreferences access. */
    private val prefsLock = Any()
    /** Whether the onResume lifecycle callback has been registered. */
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

    /**
     * Apply runtime configuration sent from the PHP layer via the `_config` key.
     * Called once per Schedule call; values that haven't changed are no-ops.
     */
    fun applyConfig(config: Map<*, *>) {
        (config["channel_id"] as? String)?.let { channelId = it }
        (config["channel_name"] as? String)?.let { channelName = it }
        (config["channel_description"] as? String)?.let { channelDescription = it }
        (config["default_sound"] as? Boolean)?.let { defaultSound = it }
        (config["tap_detection_delay_ms"] as? Number)?.let { tapDetectionDelayMs = it.toLong() }
        (config["navigation_replay_duration_ms"] as? Number)?.let { navigationReplayDurationMs = it.toLong() }
    }

    /** Holds a weak reference to the current activity for event dispatch. */
    object ActivityHolder {
        private var ref: WeakReference<FragmentActivity>? = null

        fun set(activity: FragmentActivity) {
            ref = WeakReference(activity)
        }

        fun get(): FragmentActivity? = ref?.get()
    }

    /**
     * Store a notification's tap payload in SharedPreferences.
     * Called when a notification is shown. On user tap (auto-cancel), the payload persists.
     * On user dismiss (swipe), the deleteIntent clears it via [clearTapPayload].
     */
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

    /**
     * Clear a stored tap payload. Called from deleteIntent when user swipe-dismisses
     * a notification, preventing a false NotificationTapped dispatch.
     */
    fun clearTapPayload(context: Context, id: String) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            prefs.edit().remove("tap_payload_$id").apply()
            Log.d(TAG, "Cleared tap payload for dismissed notification: $id")
        }
    }

    private fun ensureNotificationChannel(context: Context) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        // Always call createNotificationChannel — Android handles this idempotently.
        // If the channel already exists, the system updates name and description
        // but preserves user-modified settings (importance, vibration, sound).
        val channel = NotificationChannel(
            channelId,
            channelName,
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = channelDescription
            enableVibration(true)
        }
        manager.createNotificationChannel(channel)
    }

    /**
     * Dispatch an event to the PHP layer via the bridge.
     * Requires an active activity reference.
     */
    fun dispatchEvent(activity: FragmentActivity, event: String, payloadJson: String) {
        try {
            activity.runOnUiThread {
                try {
                    NativeActionCoordinator.dispatchEvent(activity, event, payloadJson)
                    // Fallback: if Livewire wasn't loaded yet, listen for livewire:init
                    // to dispatch when it becomes available (cold start timing fix)
                    injectLivewireInitFallback(activity, event, payloadJson)
                } catch (e: Exception) {
                    Log.e(TAG, "Error dispatching event on UI thread: ${e.message}", e)
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error dispatching event: ${e.message}", e)
        }
    }

    /**
     * Inject a JS fallback that listens for Livewire's `livewire:init` lifecycle event.
     * If Livewire was already loaded (and NativeActionCoordinator already dispatched),
     * this is a no-op. Otherwise, it queues the dispatch for when Livewire initializes.
     */
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

    /**
     * Register an Application.ActivityLifecycleCallbacks that checks for tapped notifications
     * on every onResume. This enables immediate warm-start tap detection without waiting for
     * the next bridge function call. Uses a 500ms delay to ensure the WebView is ready and
     * any deleteIntent broadcasts (from swipe-dismiss) have been processed.
     */
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

    /**
     * Inject a JS listener for Livewire's `livewire:navigated` event that replays a tap event.
     * When using wire:navigate (SPA-like navigation), the new page's Livewire components may not
     * be hydrated when the initial dispatchEvent JS runs. This replay ensures the event reaches
     * components on the destination page after navigation completes.
     *
     * Replays on every `livewire:navigated` for 15 seconds to cover multi-step navigation
     * (e.g., Today → Settings → Debug). After 15s the listener removes itself.
     */
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
            activity.runOnUiThread {
                webView.evaluateJavascript(js, null)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error injecting navigation replay: ${e.message}", e)
        }
    }

    /**
     * Store a pending event in SharedPreferences for dispatch when the bridge is available.
     * Events are stored as a JSON array queue to prevent overwrites.
     */
    fun storePendingEvent(context: Context, eventClass: String, payload: JSONObject) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val existing = prefs.getString(PENDING_EVENTS_KEY, null)
            val queue = if (existing != null) JSONArray(existing) else JSONArray()

            val entry = JSONObject().apply {
                put("event", eventClass)
                put("payload", payload)
            }
            queue.put(entry)

            prefs.edit().putString(PENDING_EVENTS_KEY, queue.toString()).apply()
            Log.d(TAG, "Stored pending event ($eventClass), queue size: ${queue.length()}")
        }
    }

    /**
     * Check for and dispatch any pending events that were stored while the app was inactive.
     * Also checks if the activity was launched via a notification tap (PendingIntent.getActivity)
     * and dispatches the NotificationTapped event from the intent extras.
     * Finally, detects warm-start taps via SharedPreferences-based tap payload tracking.
     */
    private fun dispatchPendingEvents(activity: FragmentActivity) {
        // Register onResume callback for immediate warm-start tap detection
        registerResumeDetection(activity)

        // Check if the activity was launched from a notification tap (cold start)
        val intent = activity.intent
        if (intent?.action == "com.ikromjon.localnotifications.TAP") {
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
                // Replay on next wire:navigate so the destination page's components receive it
                injectNavigationReplay(activity, eventClass, payloadStr)
                // Clear the tap payload to prevent duplicate dispatch from warm-start detection
                clearTapPayload(activity, id)
            }
            // Clear action to prevent re-dispatch on configuration change
            intent.action = null
        }

        // Warm-start tap detection: compare stored tap payloads against active notifications.
        // If a tap payload exists but the notification is no longer in the status bar,
        // the user must have tapped it (auto-cancel removes it but deleteIntent does NOT fire).
        detectTappedNotifications(activity)

        // Dispatch any events stored in SharedPreferences queue
        val prefs = activity.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val eventsJson = prefs.getString(PENDING_EVENTS_KEY, null) ?: return

        // Remove immediately to prevent duplicate dispatch
        prefs.edit().remove(PENDING_EVENTS_KEY).apply()

        val queue = JSONArray(eventsJson)
        Log.d(TAG, "Dispatching ${queue.length()} pending event(s) from queue")

        for (i in 0 until queue.length()) {
            val entry = queue.getJSONObject(i)
            val eventClass = entry.getString("event")
            val payload = entry.getJSONObject("payload")
            dispatchEvent(activity, eventClass, payload.toString())
        }
    }

    /**
     * Detect tapped notifications by comparing stored tap payloads against active notifications.
     * When a notification is tapped, auto-cancel removes it from the status bar but deleteIntent
     * does NOT fire, so the tap payload persists. If a payload exists but the notification is gone,
     * the user must have tapped it.
     */
    private fun detectTappedNotifications(activity: FragmentActivity) {
        val prefs = activity.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val allKeys = prefs.all.keys.filter { it.startsWith("tap_payload_") }
        if (allKeys.isEmpty()) return

        // Get currently active notification IDs from the status bar
        val notificationManager = activity.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val activeIds = notificationManager.activeNotifications.map { it.id }.toSet()

        for (key in allKeys) {
            val notifId = key.removePrefix("tap_payload_")
            val notifHashCode = notifId.hashCode()

            if (notifHashCode !in activeIds) {
                // Notification is no longer in the status bar — user tapped it
                val payloadJson = prefs.getString(key, null) ?: continue
                val eventClass = "Ikromjon\\LocalNotifications\\Events\\NotificationTapped"
                Log.d(TAG, "Detected tapped notification (warm-start): $notifId")
                dispatchEvent(activity, eventClass, payloadJson)
                // Replay on next wire:navigate so the destination page's components receive it
                injectNavigationReplay(activity, eventClass, payloadJson)
                clearTapPayload(activity, notifId)
            }
        }
    }

    /**
     * Schedule a local notification
     */
    class Schedule(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Apply runtime config from PHP layer
            (parameters["_config"] as? Map<*, *>)?.let { applyConfig(it) }

            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")
            val title = parameters["title"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: title")
            val body = parameters["body"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: body")

            val delay = (parameters["delay"] as? Number)?.toLong()
            val atTimestamp = (parameters["at"] as? Number)?.toLong()
            val repeatInterval = parameters["repeat"] as? String
            val repeatIntervalSeconds = (parameters["repeatIntervalSeconds"] as? Number)?.toLong()
            val sound = parameters["sound"] as? Boolean ?: defaultSound
            val badge = (parameters["badge"] as? Number)?.toInt()
            val data = parameters["data"] as? Map<*, *>
            val subtitle = parameters["subtitle"] as? String
            val imageUrl = parameters["image"] as? String
            val bigText = parameters["bigText"] as? String
            val actions = parameters["actions"] as? List<*>

            val repeatDaysList = (parameters["repeatDays"] as? List<*>)?.mapNotNull {
                (it as? Number)?.toInt()
            }?.filter { it in 1..7 }
            val repeatCount = (parameters["repeatCount"] as? Number)?.toInt()

            val context = activity as Context
            ensureNotificationChannel(context)

            return try {
                // Day-of-week scheduling: create one sub-alarm per day
                if (repeatDaysList != null && repeatDaysList.isNotEmpty() && atTimestamp != null) {
                    val baseDate = Calendar.getInstance().apply {
                        timeInMillis = atTimestamp * 1000
                    }
                    val subIds = mutableListOf<String>()

                    for (isoDay in repeatDaysList) {
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
                            // If the calculated time is in the past, move to next week
                            if (timeInMillis <= System.currentTimeMillis()) {
                                add(Calendar.WEEK_OF_YEAR, 1)
                            }
                        }

                        val triggerTimeMs = triggerCal.timeInMillis
                        val weekMs = AlarmManager.INTERVAL_DAY * 7

                        scheduleAlarm(context, subId, title, body, sound, badge, data, subtitle, imageUrl, bigText, actions, triggerTimeMs, weekMs, "weekly", repeatCount)
                        saveNotificationInfo(context, subId, title, body, triggerTimeMs, weekMs, "weekly", sound, badge, data, subtitle, imageUrl, bigText, actions, repeatCount)
                    }

                    // Save a parent entry that tracks the sub-IDs for cancel/getPending
                    saveRepeatDaysParent(context, id, subIds)

                    Log.d(TAG, "✅ Day-of-week notification scheduled: $id (${subIds.size} sub-alarms)")

                    val payload = JSONObject().apply {
                        put("id", id)
                        put("title", title)
                        put("body", body)
                    }
                    dispatchEvent(
                        activity,
                        "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled",
                        payload.toString()
                    )

                    mapOf("success" to true, "id" to id)
                } else {
                    // Standard single-alarm scheduling
                    val triggerTimeMs = when {
                        delay != null && delay > 0 -> System.currentTimeMillis() + (delay * 1000)
                        atTimestamp != null -> atTimestamp * 1000
                        else -> System.currentTimeMillis() + 1000
                    }

                    val repeatMs = if (repeatIntervalSeconds != null && repeatIntervalSeconds >= 60 && repeatInterval == null) {
                        repeatIntervalSeconds * 1000L
                    } else when (repeatInterval) {
                        "minute" -> 60_000L
                        "hourly" -> 3_600_000L
                        "daily" -> AlarmManager.INTERVAL_DAY
                        "weekly" -> AlarmManager.INTERVAL_DAY * 7
                        "monthly" -> -1L
                        "yearly" -> -2L
                        else -> 0L
                    }

                    scheduleAlarm(context, id, title, body, sound, badge, data, subtitle, imageUrl, bigText, actions, triggerTimeMs, repeatMs, repeatInterval, repeatCount)
                    saveNotificationInfo(context, id, title, body, triggerTimeMs, repeatMs, repeatInterval, sound, badge, data, subtitle, imageUrl, bigText, actions, repeatCount)

                    Log.d(TAG, "✅ Notification scheduled: $id at $triggerTimeMs")

                    val payload = JSONObject().apply {
                        put("id", id)
                        put("title", title)
                        put("body", body)
                    }
                    dispatchEvent(
                        activity,
                        "Ikromjon\\LocalNotifications\\Events\\NotificationScheduled",
                        payload.toString()
                    )

                    mapOf("success" to true, "id" to id)
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error scheduling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Schedule a single alarm with the given parameters.
     */
    private fun scheduleAlarm(
        context: Context,
        id: String,
        title: String,
        body: String,
        sound: Boolean,
        badge: Int?,
        data: Map<*, *>?,
        subtitle: String?,
        imageUrl: String?,
        bigText: String?,
        actions: List<*>?,
        triggerTimeMs: Long,
        repeatMs: Long,
        repeatType: String?,
        repeatCount: Int? = null
    ) {
        val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.ikromjon.localnotifications.NOTIFY"
            putExtra("notification_id", id)
            putExtra("title", title)
            putExtra("body", body)
            putExtra("sound", sound)
            putExtra("channel_id", channelId)
            if (badge != null) putExtra("badge", badge)
            if (repeatMs != 0L) putExtra("repeat_ms", repeatMs)
            if (repeatType != null) putExtra("repeat_type", repeatType)
            if (repeatCount != null) putExtra("remaining_count", repeatCount)
            if (data != null) {
                val dataJson = JSONObject(data.mapKeys { it.key.toString() }).toString()
                putExtra("data", dataJson)
            }
            if (subtitle != null) putExtra("subtitle", subtitle)
            if (imageUrl != null) putExtra("image", imageUrl)
            if (bigText != null) putExtra("big_text", bigText)
            if (actions != null) {
                putExtra("actions", serializeActions(actions).toString())
            }
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            id.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.setExactAndAllowWhileIdle(
            AlarmManager.RTC_WAKEUP,
            triggerTimeMs,
            pendingIntent
        )
    }

    /**
     * Cancel a scheduled notification by identifier.
     * If the ID is a repeatDays parent, cancels all sub-alarms.
     */
    class Cancel(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")

            val context = activity as Context
            return try {
                // Check if this is a repeatDays parent with sub-IDs
                val subIds = getRepeatDaysSubIds(context, id)
                if (subIds != null) {
                    // Cancel all sub-alarms
                    for (subId in subIds) {
                        cancelSingleAlarm(context, subId)
                        removeNotificationInfo(context, subId)
                        clearTapPayload(context, subId)
                    }
                    removeRepeatDaysParent(context, id)
                    Log.d(TAG, "✅ Day-of-week notification cancelled: $id (${subIds.size} sub-alarms)")
                } else {
                    // Cancel single alarm
                    cancelSingleAlarm(context, id)
                    removeNotificationInfo(context, id)
                    clearTapPayload(context, id)
                    Log.d(TAG, "✅ Notification cancelled: $id")
                }

                mapOf("success" to true, "id" to id)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error cancelling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }

        private fun cancelSingleAlarm(context: Context, id: String) {
            val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
                action = "com.ikromjon.localnotifications.NOTIFY"
            }
            val requestCode = id.hashCode()
            val pendingIntent = PendingIntent.getBroadcast(
                context,
                requestCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
            alarmManager.cancel(pendingIntent)
            pendingIntent.cancel()

            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.cancel(requestCode)
        }
    }

    /**
     * Cancel all scheduled notifications
     */
    class CancelAll(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            val context = activity as Context
            return try {
                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

                val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager

                for (id in allIds) {
                    val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
                        action = "com.ikromjon.localnotifications.NOTIFY"
                    }
                    val pendingIntent = PendingIntent.getBroadcast(
                        context,
                        id.hashCode(),
                        intent,
                        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                    )
                    alarmManager.cancel(pendingIntent)
                    pendingIntent.cancel()
                }

                // Clear persisted storage
                prefs.edit().clear().apply()

                // Cancel all delivered notifications on this channel
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

    /**
     * Get all pending scheduled notifications.
     * repeatDays sub-alarms are aggregated back into a single parent entry.
     */
    class GetPending(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            val context = activity as Context
            return try {
                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

                // Collect sub-IDs that belong to a parent so we can skip them
                val parentIds = prefs.getStringSet("repeat_days_parent_ids", emptySet()) ?: emptySet()
                val subIdSet = mutableSetOf<String>()
                for (parentId in parentIds) {
                    val subIds = getRepeatDaysSubIds(context, parentId)
                    if (subIds != null) {
                        subIdSet.addAll(subIds)
                    }
                }

                val notifications = JSONArray()

                // Add non-sub-ID notifications
                for (id in allIds) {
                    if (id in subIdSet) continue
                    val infoJson = prefs.getString("notification_$id", null) ?: continue
                    val info = JSONObject(infoJson)
                    notifications.put(info)
                }

                // Add aggregated parent entries for repeatDays
                for (parentId in parentIds) {
                    val subIds = getRepeatDaysSubIds(context, parentId) ?: continue
                    // Use the first sub-alarm's info as the base, add repeatDays metadata
                    val firstSubId = subIds.firstOrNull() ?: continue
                    val firstInfoJson = prefs.getString("notification_$firstSubId", null) ?: continue
                    val parentInfo = JSONObject(firstInfoJson)
                    parentInfo.put("id", parentId)

                    // Collect the days from sub-IDs
                    val days = JSONArray()
                    for (subId in subIds) {
                        val dayStr = subId.substringAfterLast("_day_")
                        days.put(dayStr.toIntOrNull() ?: continue)
                    }
                    parentInfo.put("repeatDays", days)

                    notifications.put(parentInfo)
                }

                mapOf(
                    "success" to true,
                    "notifications" to notifications.toString(),
                    "count" to notifications.length()
                )
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error getting pending notifications: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Request notification permission (Android 13+)
     */
    class RequestPermission(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    val hasPermission = ContextCompat.checkSelfPermission(
                        activity,
                        Manifest.permission.POST_NOTIFICATIONS
                    ) == PackageManager.PERMISSION_GRANTED

                    if (hasPermission) {
                        Log.d(TAG, "✅ Notification permission already granted")

                        dispatchEvent(
                            activity,
                            "Ikromjon\\LocalNotifications\\Events\\PermissionGranted",
                            "{}"
                        )

                        return mapOf("granted" to true)
                    }

                    activity.requestPermissions(
                        arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                        1001
                    )

                    // Return pending since the result comes asynchronously
                    mapOf("granted" to false, "status" to "pending")
                } else {
                    // Pre-Android 13, notifications are allowed by default
                    Log.d(TAG, "✅ Notification permission granted (pre-Android 13)")

                    dispatchEvent(
                        activity,
                        "Ikromjon\\LocalNotifications\\Events\\PermissionGranted",
                        "{}"
                    )

                    mapOf("granted" to true)
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error requesting permission: ${e.message}", e)
                mapOf("granted" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Check current notification permission status
     */
    class CheckPermission(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Store activity reference and dispatch any pending tap events
            ActivityHolder.set(activity)
            dispatchPendingEvents(activity)

            val context = activity as Context
            return try {
                val status = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    val hasPermission = ContextCompat.checkSelfPermission(
                        context,
                        Manifest.permission.POST_NOTIFICATIONS
                    ) == PackageManager.PERMISSION_GRANTED

                    if (hasPermission) "granted" else "denied"
                } else {
                    val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                    if (notificationManager.areNotificationsEnabled()) "granted" else "denied"
                }

                mapOf("status" to status)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error checking permission: ${e.message}", e)
                mapOf("status" to "unknown", "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    // -- Helper functions --

    /**
     * Serialize a list of action maps into a JSONArray.
     */
    private fun serializeActions(actions: List<*>): JSONArray {
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

    private fun saveNotificationInfo(
        context: Context,
        id: String,
        title: String,
        body: String,
        triggerTimeMs: Long,
        repeatMs: Long,
        repeatType: String? = null,
        sound: Boolean,
        badge: Int?,
        data: Map<*, *>?,
        subtitle: String? = null,
        imageUrl: String? = null,
        bigText: String? = null,
        actions: List<*>? = null,
        remainingCount: Int? = null
    ) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.add(id)

            val info = JSONObject().apply {
                put("id", id)
                put("title", title)
                put("body", body)
                put("triggerTimeMs", triggerTimeMs)
                put("repeatMs", repeatMs)
                put("channelId", channelId)
                if (repeatType != null) put("repeatType", repeatType)
                if (remainingCount != null) put("remainingCount", remainingCount)
                put("sound", sound)
                if (badge != null) put("badge", badge)
                if (data != null) put("data", JSONObject(data.mapKeys { it.key.toString() }))
                if (subtitle != null) put("subtitle", subtitle)
                if (imageUrl != null) put("image", imageUrl)
                if (bigText != null) put("bigText", bigText)
                if (actions != null) {
                    put("actions", serializeActions(actions))
                }
            }

            prefs.edit()
                .putStringSet("notification_ids", ids)
                .putString("notification_$id", info.toString())
                .apply()
        }
    }

    /**
     * Calculate the next trigger time for calendar-based repeat types (monthly/yearly).
     * Handles variable month lengths and leap years.
     */
    fun calculateNextTrigger(repeatType: String, currentTriggerMs: Long): Long {
        val cal = Calendar.getInstance().apply { timeInMillis = currentTriggerMs }
        when (repeatType) {
            "monthly" -> cal.add(Calendar.MONTH, 1)
            "yearly" -> cal.add(Calendar.YEAR, 1)
        }
        return cal.timeInMillis
    }

    /**
     * Save a parent entry that maps a logical notification ID to its day-of-week sub-IDs.
     * Used by Cancel and GetPending to aggregate sub-alarms.
     */
    private fun saveRepeatDaysParent(context: Context, parentId: String, subIds: List<String>) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val parentIds = prefs.getStringSet("repeat_days_parent_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        parentIds.add(parentId)

        val subIdsArray = JSONArray(subIds)
        prefs.edit()
            .putStringSet("repeat_days_parent_ids", parentIds)
            .putString("repeat_days_$parentId", subIdsArray.toString())
            .apply()
    }

    /**
     * Get the sub-IDs for a repeatDays parent, or null if this is not a parent ID.
     */
    fun getRepeatDaysSubIds(context: Context, parentId: String): List<String>? {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val json = prefs.getString("repeat_days_$parentId", null) ?: return null
        val arr = JSONArray(json)
        return (0 until arr.length()).map { arr.getString(it) }
    }

    private fun removeRepeatDaysParent(context: Context, parentId: String) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val parentIds = prefs.getStringSet("repeat_days_parent_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        parentIds.remove(parentId)

        prefs.edit()
            .putStringSet("repeat_days_parent_ids", parentIds)
            .remove("repeat_days_$parentId")
            .apply()
    }

    private fun removeNotificationInfo(context: Context, id: String) {
        synchronized(prefsLock) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)

            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        }
    }
}
