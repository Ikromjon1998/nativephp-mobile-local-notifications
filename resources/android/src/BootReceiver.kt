package com.nativephp.localnotifications

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import org.json.JSONObject
/**
 * BroadcastReceiver that restores scheduled notifications after device reboot.
 * Alarms are cleared when the device restarts, so we re-schedule them from persisted storage.
 */
class BootReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "BootReceiver"
    }

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) return

        Log.d(TAG, "Device rebooted, restoring scheduled notifications")

        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val allIds = prefs.getStringSet(PrefsKeys.NOTIFICATION_IDS, emptySet()) ?: emptySet()

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        val now = System.currentTimeMillis()

        for (id in allIds) {
            try {
                val infoJson = prefs.getString(PrefsKeys.notificationInfo(id), null) ?: continue
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

                val channelId = if (info.has("channelId")) info.getString("channelId") else Defaults.CHANNEL_ID

                val soundName = if (info.has("soundName")) info.getString("soundName") else null
                val priority = if (info.has("priority")) info.getString("priority") else null
                val silent = info.optBoolean("silent", false)

                val notifyIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
                    action = IntentActions.NOTIFY
                    putExtra(IntentExtras.NOTIFICATION_ID, id)
                    putExtra(IntentExtras.TITLE, title)
                    putExtra(IntentExtras.BODY, body)
                    putExtra(IntentExtras.SOUND, sound)
                    if (soundName != null) putExtra(IntentExtras.SOUND_NAME, soundName)
                    putExtra(IntentExtras.CHANNEL_ID, channelId)
                    if (repeatMs != 0L) putExtra(IntentExtras.REPEAT_MS, repeatMs)
                    if (repeatType != null) putExtra(IntentExtras.REPEAT_TYPE, repeatType)
                    if (remainingCount > 0) putExtra(IntentExtras.REMAINING_COUNT, remainingCount)
                    if (info.has("data")) putExtra(IntentExtras.DATA, info.getString("data"))
                    if (info.has("subtitle")) putExtra(IntentExtras.SUBTITLE, info.getString("subtitle"))
                    if (info.has("image")) putExtra(IntentExtras.IMAGE, info.getString("image"))
                    if (info.has("bigText")) putExtra(IntentExtras.BIG_TEXT, info.getString("bigText"))
                    info.optJSONArray("actions")?.let { putExtra(IntentExtras.ACTIONS, it.toString()) }
                    if (priority != null) putExtra(IntentExtras.PRIORITY, priority)
                    if (silent) putExtra(IntentExtras.SILENT, true)
                }

                val pendingIntent = PendingIntent.getBroadcast(
                    context,
                    id.hashCode(),
                    notifyIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                // For repeating notifications, use the next future trigger time
                val adjustedTrigger = if (repeatMs != 0L && triggerTimeMs < now) {
                    if (repeatType == RepeatType.MONTHLY || repeatType == RepeatType.YEARLY) {
                        // Advance using Calendar until we're in the future
                        var next = triggerTimeMs
                        while (next < now) {
                            next = NotificationScheduler.calculateNextTrigger(repeatType, next)
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
