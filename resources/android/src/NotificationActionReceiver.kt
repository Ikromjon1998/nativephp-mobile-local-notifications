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
        val notificationId = intent.getStringExtra(IntentExtras.NOTIFICATION_ID) ?: return
        val actionId = intent.getStringExtra(IntentExtras.ACTION_ID) ?: return
        val dataJson = intent.getStringExtra(IntentExtras.NOTIFICATION_DATA)

        Log.d(TAG, "Action pressed: $actionId on notification: $notificationId")

        // Check for text input from RemoteInput
        val remoteInputBundle = RemoteInput.getResultsFromIntent(intent)
        val inputText = remoteInputBundle?.getCharSequence(REMOTE_INPUT_KEY)?.toString()

        // Build the event payload
        val payload = JSONObject().apply {
            put("notificationId", SnoozeId.strip(notificationId))
            put("actionId", actionId)
            if (dataJson != null) {
                try {
                    put("data", JSONObject(dataJson))
                } catch (e: org.json.JSONException) {
                    Log.e(TAG, "Invalid notification data JSON for $notificationId: ${e.message}")
                }
            }
            if (inputText != null) {
                put("inputText", inputText)
            }
        }

        // Handle native snooze rescheduling before dismissing
        val snoozeSecs = intent.getIntExtra(IntentExtras.SNOOZE_SECONDS, 0)
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
                Events.NOTIFICATION_ACTION_PRESSED,
                payload.toString()
            )
        } else {
            // Store as pending event — will be flushed when user next opens the app.
            // Do NOT launch the app: startActivity() from a BroadcastReceiver is
            // restricted on Android 12+ (API 31) and the user may not want the app
            // to open on every action button press.
            LocalNotificationsFunctions.storePendingEvent(
                context,
                Events.NOTIFICATION_ACTION_PRESSED,
                payload
            )
            Log.d(TAG, "App not active, stored pending ActionPressed event for: $notificationId")
        }
    }

    /**
     * Reschedule the notification via AlarmManager for a snooze delay.
     * Uses a dedicated "{id}_snooze" sub-ID so the snooze is a one-shot side
     * alarm: it never overwrites the original notification's PendingIntent
     * (snoozing a repeating notification must not kill its repeat chain) and
     * its post-fire cleanup removes only the snooze entry. The snooze is
     * persisted so it appears in getPending() and survives a reboot via
     * BootReceiver.
     */
    private fun rescheduleSnooze(context: Context, intent: Intent, snoozeSecs: Int) {
        val originalId = intent.getStringExtra(IntentExtras.NOTIFICATION_ID) ?: return
        val title = intent.getStringExtra(IntentExtras.TITLE) ?: return
        val body = intent.getStringExtra(IntentExtras.BODY) ?: return
        val sound = intent.getBooleanExtra(IntentExtras.SOUND, true)
        val soundName = intent.getStringExtra(IntentExtras.SOUND_NAME)
        val channelId = intent.getStringExtra(IntentExtras.CHANNEL_ID) ?: Defaults.CHANNEL_ID
        val dataJson = intent.getStringExtra(IntentExtras.NOTIFICATION_DATA)
        val subtitle = intent.getStringExtra(IntentExtras.SUBTITLE)
        val imageUrl = intent.getStringExtra(IntentExtras.IMAGE)
        val bigText = intent.getStringExtra(IntentExtras.BIG_TEXT)
        val actionsJson = intent.getStringExtra(IntentExtras.ACTIONS)
        val priority = intent.getStringExtra(IntentExtras.PRIORITY)
        val silent = intent.getBooleanExtra(IntentExtras.SILENT, false)

        // Snoozing an already-snoozed notification re-uses the same sub-ID.
        val snoozeId = SnoozeId.forId(originalId)

        val triggerMs = System.currentTimeMillis() + (snoozeSecs * 1000L)

        val rescheduleIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = IntentActions.NOTIFY
            putExtra(IntentExtras.NOTIFICATION_ID, snoozeId)
            putExtra(IntentExtras.TITLE, title)
            putExtra(IntentExtras.BODY, body)
            putExtra(IntentExtras.SOUND, sound)
            if (soundName != null) putExtra(IntentExtras.SOUND_NAME, soundName)
            putExtra(IntentExtras.CHANNEL_ID, channelId)
            // No repeat — snooze is a one-shot reschedule
            putExtra(IntentExtras.REPEAT_MS, 0L)
            if (dataJson != null) putExtra(IntentExtras.DATA, dataJson)
            if (subtitle != null) putExtra(IntentExtras.SUBTITLE, subtitle)
            if (imageUrl != null) putExtra(IntentExtras.IMAGE, imageUrl)
            if (bigText != null) putExtra(IntentExtras.BIG_TEXT, bigText)
            if (actionsJson != null) putExtra(IntentExtras.ACTIONS, actionsJson)
            if (priority != null) putExtra(IntentExtras.PRIORITY, priority)
            if (silent) putExtra(IntentExtras.SILENT, true)
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            snoozeId.hashCode(),
            rescheduleIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.setExactAndAllowWhileIdle(
            AlarmManager.RTC_WAKEUP,
            triggerMs,
            pendingIntent
        )

        // Persist so getPending() lists the snoozed alarm and BootReceiver
        // restores it if the device reboots before it fires.
        val params = NotificationParams(
            id = snoozeId,
            title = title,
            body = body,
            sound = sound,
            soundName = soundName,
            badge = null,
            data = dataJson?.let {
                try {
                    val obj = JSONObject(it)
                    obj.keys().asSequence().associateWith { key -> obj.get(key) }
                } catch (e: org.json.JSONException) {
                    null
                }
            },
            subtitle = subtitle,
            imageUrl = imageUrl,
            bigText = bigText,
            actions = actionsJson?.let { NotificationScheduler.coerceToList(it) },
            priority = priority,
            silent = silent,
        )
        NotificationScheduler.saveNotificationInfo(context, snoozeId, params, triggerMs, 0L, null, null, channelId)

        Log.d(TAG, "Rescheduled snooze for $originalId as $snoozeId: fires in ${snoozeSecs}s")
    }
}
