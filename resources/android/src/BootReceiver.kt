package com.nativephp.localnotifications

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import org.json.JSONObject
import java.util.Calendar

/**
 * BroadcastReceiver that restores scheduled notifications after device reboot.
 * Alarms are cleared when the device restarts, so we re-schedule them from persisted storage.
 */
class BootReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "BootReceiver"
        private const val PREFS_NAME = "nativephp_local_notifications_prefs"
    }

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) return

        Log.d(TAG, "Device rebooted, restoring scheduled notifications")

        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val allIds = prefs.getStringSet("notification_ids", emptySet()) ?: emptySet()

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        val now = System.currentTimeMillis()

        for (id in allIds) {
            try {
                val infoJson = prefs.getString("notification_$id", null) ?: continue
                val info = JSONObject(infoJson)

                val triggerTimeMs = info.getLong("triggerTimeMs")
                val repeatMs = info.optLong("repeatMs", 0L)
                val repeatType = if (info.has("repeatType")) info.getString("repeatType") else null
                val remainingCount = if (info.has("remainingCount")) info.getInt("remainingCount") else -1
                val title = info.getString("title")
                val body = info.getString("body")
                val sound = info.optBoolean("sound", true)

                // Skip non-repeating notifications that are in the past
                if (repeatMs == 0L && triggerTimeMs < now) {
                    Log.d(TAG, "Skipping expired notification: $id")
                    continue
                }

                val channelId = if (info.has("channelId")) info.getString("channelId") else "nativephp_local_notifications"

                val notifyIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
                    action = "com.nativephp.localnotifications.NOTIFY"
                    putExtra("notification_id", id)
                    putExtra("title", title)
                    putExtra("body", body)
                    putExtra("sound", sound)
                    putExtra("channel_id", channelId)
                    if (repeatMs != 0L) putExtra("repeat_ms", repeatMs)
                    if (repeatType != null) putExtra("repeat_type", repeatType)
                    if (remainingCount > 0) putExtra("remaining_count", remainingCount)
                    if (info.has("data")) putExtra("data", info.getString("data"))
                    if (info.has("subtitle")) putExtra("subtitle", info.getString("subtitle"))
                    if (info.has("image")) putExtra("image", info.getString("image"))
                    if (info.has("bigText")) putExtra("big_text", info.getString("bigText"))
                    if (info.has("actions")) putExtra("actions", info.getJSONArray("actions").toString())
                }

                val pendingIntent = PendingIntent.getBroadcast(
                    context,
                    id.hashCode(),
                    notifyIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                // For repeating notifications, use the next future trigger time
                val adjustedTrigger = if (repeatMs != 0L && triggerTimeMs < now) {
                    if (repeatType == "monthly" || repeatType == "yearly") {
                        // Advance using Calendar until we're in the future
                        var next = triggerTimeMs
                        while (next < now) {
                            next = LocalNotificationsFunctions.calculateNextTrigger(repeatType, next)
                        }
                        next
                    } else {
                        val elapsed = now - triggerTimeMs
                        val periods = (elapsed / repeatMs) + 1
                        triggerTimeMs + (periods * repeatMs)
                    }
                } else {
                    triggerTimeMs
                }

                // Always use exact alarms — repeating notifications are
                // self-rescheduled by LocalNotificationReceiver after each delivery
                alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.RTC_WAKEUP,
                    adjustedTrigger,
                    pendingIntent
                )

                Log.d(TAG, "Restored notification: $id")
            } catch (e: Exception) {
                Log.e(TAG, "Error restoring notification $id: ${e.message}", e)
            }
        }
    }
}
