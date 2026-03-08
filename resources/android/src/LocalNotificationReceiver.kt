package com.ikromjon.localnotifications

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.app.NotificationCompat

/**
 * BroadcastReceiver that fires local notifications when the AlarmManager triggers.
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
        val channelId = intent.getStringExtra("channel_id") ?: "nativephp_local_notifications"

        Log.d(TAG, "Notification received: $id - $title")

        // Build the notification
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Create an intent to open the app when notification is tapped
        val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)?.apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("notification_id", id)
            putExtra("notification_title", title)
            putExtra("notification_body", body)
            val data = intent.getStringExtra("data")
            if (data != null) putExtra("notification_data", data)
        }

        val pendingIntent = if (launchIntent != null) {
            PendingIntent.getActivity(
                context,
                id.hashCode(),
                launchIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        } else null

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

        if (pendingIntent != null) {
            builder.setContentIntent(pendingIntent)
        }

        if (!sound) {
            builder.setSilent(true)
        }

        notificationManager.notify(id.hashCode(), builder.build())

        // Clean up non-repeating notifications from storage
        val repeatMs = intent.getLongExtra("repeat_ms", 0L)
        if (repeatMs == 0L) {
            val prefs = context.getSharedPreferences("nativephp_local_notifications_prefs", Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)
            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        }
    }
}
