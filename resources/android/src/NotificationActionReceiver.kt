package com.nativephp.localnotifications

import android.app.AlarmManager
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.app.RemoteInput
import org.json.JSONObject

/**
 * BroadcastReceiver that handles notification action button presses.
 * Dispatches the NotificationActionPressed event to the PHP layer.
 * Supports native snooze: if the action has a snooze duration, the notification
 * is rescheduled via AlarmManager without needing the app to be open.
 */
class NotificationActionReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "NotifActionReceiver"
        const val REMOTE_INPUT_KEY = "notification_action_input"
    }

    override fun onReceive(context: Context, intent: Intent) {
        val notificationId = intent.getStringExtra("notification_id") ?: return
        val actionId = intent.getStringExtra("action_id") ?: return
        val dataJson = intent.getStringExtra("notification_data")

        Log.d(TAG, "Action pressed: $actionId on notification: $notificationId")

        // Check for text input from RemoteInput
        val remoteInputBundle = RemoteInput.getResultsFromIntent(intent)
        val inputText = remoteInputBundle?.getCharSequence(REMOTE_INPUT_KEY)?.toString()

        // Build the event payload
        val payload = JSONObject().apply {
            put("notificationId", notificationId)
            put("actionId", actionId)
            if (dataJson != null) {
                put("data", JSONObject(dataJson))
            }
            if (inputText != null) {
                put("inputText", inputText)
            }
        }

        // Handle native snooze rescheduling before dismissing
        val snoozeSecs = intent.getIntExtra("snooze_seconds", 0)
        if (snoozeSecs > 0) {
            rescheduleSnooze(context, intent, snoozeSecs)
            payload.put("snoozed", true)
            payload.put("snoozeSeconds", snoozeSecs)
            Log.d(TAG, "Snooze scheduled: $notificationId in ${snoozeSecs}s")
        }

        // Dismiss the notification and clear the tap payload so detectTappedNotifications()
        // doesn't falsely think the user tapped it (programmatic cancel != user tap).
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.cancel(notificationId.hashCode())
        LocalNotificationsFunctions.clearTapPayload(context, notificationId)

        // Try to dispatch the event immediately if the app is active
        val activity = LocalNotificationsFunctions.ActivityHolder.get()
        if (activity != null) {
            LocalNotificationsFunctions.dispatchEvent(
                activity,
                "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed",
                payload.toString()
            )
        } else {
            // Store as pending event — will be flushed when user next opens the app.
            // Do NOT launch the app: startActivity() from a BroadcastReceiver is
            // restricted on Android 12+ (API 31) and the user may not want the app
            // to open on every action button press.
            LocalNotificationsFunctions.storePendingEvent(
                context,
                "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed",
                payload
            )
            Log.d(TAG, "App not active, stored pending ActionPressed event for: $notificationId")
        }
    }

    /**
     * Reschedule the notification via AlarmManager for a snooze delay.
     * Re-uses the same notification ID so the snoozed notification replaces the original.
     */
    private fun rescheduleSnooze(context: Context, intent: Intent, snoozeSecs: Int) {
        val id = intent.getStringExtra("notification_id") ?: return
        val title = intent.getStringExtra("title") ?: return
        val body = intent.getStringExtra("body") ?: return
        val sound = intent.getBooleanExtra("sound", true)
        val soundName = intent.getStringExtra("sound_name")
        val channelId = intent.getStringExtra("channel_id") ?: "nativephp_local_notifications"
        val dataJson = intent.getStringExtra("notification_data")
        val subtitle = intent.getStringExtra("subtitle")
        val imageUrl = intent.getStringExtra("image")
        val bigText = intent.getStringExtra("big_text")
        val actionsJson = intent.getStringExtra("actions")

        val triggerMs = System.currentTimeMillis() + (snoozeSecs * 1000L)

        val rescheduleIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.nativephp.localnotifications.NOTIFY"
            putExtra("notification_id", id)
            putExtra("title", title)
            putExtra("body", body)
            putExtra("sound", sound)
            if (soundName != null) putExtra("sound_name", soundName)
            putExtra("channel_id", channelId)
            // No repeat — snooze is a one-shot reschedule
            putExtra("repeat_ms", 0L)
            if (dataJson != null) putExtra("data", dataJson)
            if (subtitle != null) putExtra("subtitle", subtitle)
            if (imageUrl != null) putExtra("image", imageUrl)
            if (bigText != null) putExtra("big_text", bigText)
            if (actionsJson != null) putExtra("actions", actionsJson)
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            id.hashCode(),
            rescheduleIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.setExactAndAllowWhileIdle(
            AlarmManager.RTC_WAKEUP,
            triggerMs,
            pendingIntent
        )

        Log.d(TAG, "Rescheduled snooze for $id: fires in ${snoozeSecs}s")
    }
}
