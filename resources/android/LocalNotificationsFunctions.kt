package com.ikromjon.localnotifications

import android.Manifest
import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONArray
import org.json.JSONObject

/**
 * Functions related to local notification operations
 * Namespace: "LocalNotifications.*"
 */
object LocalNotificationsFunctions {

    private const val TAG = "LocalNotifications"
    private const val CHANNEL_ID = "nativephp_local_notifications"
    private const val CHANNEL_NAME = "Local Notifications"
    private const val PREFS_NAME = "nativephp_local_notifications_prefs"

    private fun ensureNotificationChannel(context: Context) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (manager.getNotificationChannel(CHANNEL_ID) == null) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "Notifications scheduled by the app"
                enableVibration(true)
            }
            manager.createNotificationChannel(channel)
            Log.d(TAG, "Notification channel created: $CHANNEL_ID")
        }
    }

    /**
     * Schedule a local notification
     * Parameters:
     *   - id: string - Unique identifier for this notification
     *   - title: string - Notification title
     *   - body: string - Notification body text
     *   - delay: (optional) int - Delay in seconds from now
     *   - at: (optional) int - Unix timestamp to fire at
     *   - repeat: (optional) string - Repeat interval: "minute", "hourly", "daily", "weekly"
     *   - sound: (optional) boolean - Play sound (default: true)
     *   - badge: (optional) int - Badge number
     *   - data: (optional) object - Custom data payload
     * Returns:
     *   - success: boolean
     */
    class Schedule(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")
            val title = parameters["title"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: title")
            val body = parameters["body"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: body")

            val delay = (parameters["delay"] as? Number)?.toLong()
            val atTimestamp = (parameters["at"] as? Number)?.toLong()
            val repeatInterval = parameters["repeat"] as? String
            val sound = parameters["sound"] as? Boolean ?: true
            val badge = (parameters["badge"] as? Number)?.toInt()
            val data = parameters["data"] as? Map<*, *>

            val context = activity as Context
            ensureNotificationChannel(context)

            return try {
                // Calculate trigger time
                val triggerTimeMs = when {
                    delay != null && delay > 0 -> System.currentTimeMillis() + (delay * 1000)
                    atTimestamp != null -> atTimestamp * 1000
                    else -> System.currentTimeMillis() + 1000 // fire in 1 second
                }

                // Calculate repeat interval in ms
                val repeatMs = when (repeatInterval) {
                    "minute" -> 60_000L
                    "hourly" -> 3_600_000L
                    "daily" -> AlarmManager.INTERVAL_DAY
                    "weekly" -> AlarmManager.INTERVAL_DAY * 7
                    else -> 0L
                }

                // Create intent for the alarm receiver
                val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
                    action = "com.ikromjon.localnotifications.NOTIFY"
                    putExtra("notification_id", id)
                    putExtra("title", title)
                    putExtra("body", body)
                    putExtra("sound", sound)
                    putExtra("channel_id", CHANNEL_ID)
                    if (badge != null) putExtra("badge", badge)
                    if (repeatMs > 0) putExtra("repeat_ms", repeatMs)
                    if (data != null) {
                        val dataJson = JSONObject(data.mapKeys { it.key.toString() }).toString()
                        putExtra("data", dataJson)
                    }
                }

                val requestCode = id.hashCode()
                val pendingIntent = PendingIntent.getBroadcast(
                    context,
                    requestCode,
                    intent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager

                if (repeatMs > 0) {
                    alarmManager.setRepeating(
                        AlarmManager.RTC_WAKEUP,
                        triggerTimeMs,
                        repeatMs,
                        pendingIntent
                    )
                } else {
                    alarmManager.setExactAndAllowWhileIdle(
                        AlarmManager.RTC_WAKEUP,
                        triggerTimeMs,
                        pendingIntent
                    )
                }

                // Persist notification info for getPending and boot restoration
                saveNotificationInfo(context, id, title, body, triggerTimeMs, repeatMs, sound, badge, data)

                Log.d(TAG, "✅ Notification scheduled: $id at $triggerTimeMs")

                // Fire event
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
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error scheduling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Cancel a scheduled notification by identifier
     */
    class Cancel(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
                ?: return mapOf("success" to false, "error" to "Missing required parameter: id")

            return try {
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

                // Remove from persisted storage
                removeNotificationInfo(context, id)

                // Cancel any delivered notification
                val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                notificationManager.cancel(requestCode)

                Log.d(TAG, "✅ Notification cancelled: $id")
                mapOf("success" to true, "id" to id)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Error cancelling notification: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Cancel all scheduled notifications
     */
    class CancelAll(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
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
     * Get all pending scheduled notifications
     */
    class GetPending(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return try {
                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

                val notifications = JSONArray()
                for (id in allIds) {
                    val infoJson = prefs.getString("notification_$id", null) ?: continue
                    val info = JSONObject(infoJson)
                    notifications.put(info)
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
    class CheckPermission(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
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

    private fun saveNotificationInfo(
        context: Context,
        id: String,
        title: String,
        body: String,
        triggerTimeMs: Long,
        repeatMs: Long,
        sound: Boolean,
        badge: Int?,
        data: Map<*, *>?
    ) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        ids.add(id)

        val info = JSONObject().apply {
            put("id", id)
            put("title", title)
            put("body", body)
            put("triggerTimeMs", triggerTimeMs)
            put("repeatMs", repeatMs)
            put("sound", sound)
            if (badge != null) put("badge", badge)
            if (data != null) put("data", JSONObject(data.mapKeys { it.key.toString() }))
        }

        prefs.edit()
            .putStringSet("notification_ids", ids)
            .putString("notification_$id", info.toString())
            .apply()
    }

    private fun removeNotificationInfo(context: Context, id: String) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        ids.remove(id)

        prefs.edit()
            .putStringSet("notification_ids", ids)
            .remove("notification_$id")
            .apply()
    }

    private fun dispatchEvent(activity: FragmentActivity, event: String, payloadJson: String) {
        try {
            NativeActionCoordinator.dispatchEvent(activity, event, payloadJson)
        } catch (e: Exception) {
            Log.e(TAG, "Error dispatching event: ${e.message}", e)
        }
    }
}
