package com.ikromjon.localnotifications

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.app.NotificationCompat
import org.json.JSONObject

/**
 * BroadcastReceiver that fires local notifications when the AlarmManager triggers.
 * After displaying the notification, dispatches a NotificationReceived event.
 */
class LocalNotificationReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "LocalNotifReceiver"
    }

    override fun onReceive(context: Context, intent: Intent) {
        val id = intent.getStringExtra("notification_id") ?: return
        val title = intent.getStringExtra("title") ?: return
        val body = intent.getStringExtra("body") ?: return
        val sound = intent.getBooleanExtra("sound", true)
        val channelId = intent.getStringExtra("channel_id") ?: LocalNotificationsFunctions.CHANNEL_ID
        val dataJson = intent.getStringExtra("data")

        Log.d(TAG, "Notification received: $id - $title")

        // Build the notification
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Create an intent for the tap receiver to handle user taps
        val tapIntent = Intent(context, NotificationTapReceiver::class.java).apply {
            action = "com.ikromjon.localnotifications.TAP"
            putExtra("notification_id", id)
            putExtra("notification_title", title)
            putExtra("notification_body", body)
            if (dataJson != null) putExtra("notification_data", dataJson)
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            id.hashCode(),
            tapIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        // Use app's small icon, fall back to Android's default icon
        val appIcon = try {
            val iconResId = context.applicationInfo.icon
            if (iconResId != 0) iconResId else android.R.drawable.ic_dialog_info
        } catch (e: Exception) {
            android.R.drawable.ic_dialog_info
        }

        val builder = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(appIcon)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)

        if (!sound) {
            builder.setSilent(true)
        }

        notificationManager.notify(id.hashCode(), builder.build())

        // Dispatch NotificationReceived event if the app is active
        val activity = LocalNotificationsFunctions.ActivityHolder.get()
        if (activity != null) {
            val payload = JSONObject().apply {
                put("id", id)
                put("title", title)
                put("body", body)
                if (dataJson != null) {
                    put("data", JSONObject(dataJson))
                }
            }
            LocalNotificationsFunctions.dispatchEvent(
                activity,
                "Ikromjon\\LocalNotifications\\Events\\NotificationReceived",
                payload.toString()
            )
        } else {
            Log.d(TAG, "No active activity, skipping NotificationReceived event dispatch")
        }

        // Clean up non-repeating notifications from storage
        val repeatMs = intent.getLongExtra("repeat_ms", 0L)
        if (repeatMs == 0L) {
            val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)
            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        }
    }
}
