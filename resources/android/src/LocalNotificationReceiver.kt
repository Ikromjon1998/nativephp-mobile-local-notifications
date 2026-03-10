package com.ikromjon.localnotifications

import android.app.AlarmManager
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.RemoteInput
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.Calendar

/**
 * BroadcastReceiver that fires local notifications when the AlarmManager triggers.
 * Supports rich content: images (BigPictureStyle), big text (BigTextStyle), and subtitles.
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
        val subtitle = intent.getStringExtra("subtitle")
        val imageUrl = intent.getStringExtra("image")
        val bigText = intent.getStringExtra("big_text")
        val actionsJson = intent.getStringExtra("actions")

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

        if (subtitle != null) {
            builder.setSubText(subtitle)
        }

        if (!sound) {
            builder.setSilent(true)
        }

        // Apply rich content styles
        val imageBitmap = if (imageUrl != null) downloadImage(imageUrl) else null

        if (imageBitmap != null) {
            // BigPictureStyle for image notifications
            val style = NotificationCompat.BigPictureStyle()
                .bigPicture(imageBitmap)
                .setSummaryText(bigText ?: body)
            builder.setStyle(style)
                .setLargeIcon(imageBitmap)
        } else if (bigText != null) {
            // BigTextStyle for expanded text
            val style = NotificationCompat.BigTextStyle()
                .bigText(bigText)
            builder.setStyle(style)
        }

        // Add action buttons if provided
        if (actionsJson != null) {
            try {
                val actions = JSONArray(actionsJson)
                for (i in 0 until minOf(actions.length(), 3)) {
                    val action = actions.getJSONObject(i)
                    val actionId = action.getString("id")
                    val actionTitle = action.getString("title")
                    val isInput = action.optBoolean("input", false)

                    val actionIntent = Intent(context, NotificationActionReceiver::class.java).apply {
                        this.action = "com.ikromjon.localnotifications.ACTION"
                        putExtra("notification_id", id)
                        putExtra("action_id", actionId)
                        if (dataJson != null) putExtra("notification_data", dataJson)
                    }

                    val actionPendingIntent = PendingIntent.getBroadcast(
                        context,
                        (id + actionId).hashCode(),
                        actionIntent,
                        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
                    )

                    if (isInput) {
                        val remoteInput = RemoteInput.Builder(NotificationActionReceiver.REMOTE_INPUT_KEY)
                            .setLabel(actionTitle)
                            .build()
                        val notifAction = NotificationCompat.Action.Builder(0, actionTitle, actionPendingIntent)
                            .addRemoteInput(remoteInput)
                            .build()
                        builder.addAction(notifAction)
                    } else {
                        builder.addAction(0, actionTitle, actionPendingIntent)
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error parsing actions: ${e.message}")
            }
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

        val repeatMs = intent.getLongExtra("repeat_ms", 0L)
        val repeatType = intent.getStringExtra("repeat_type")
        if (repeatMs == 0L) {
            // Clean up non-repeating notifications from storage
            val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)
            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        } else {
            // Self-reschedule the next occurrence for repeating notifications.
            // This replaces setRepeating() which is unreliable on modern Android.
            rescheduleNext(context, id, title, body, sound, channelId, repeatMs, repeatType, dataJson, subtitle, imageUrl, bigText, actionsJson)
        }
    }

    /**
     * Reschedule the next occurrence of a repeating notification using setExactAndAllowWhileIdle.
     * For monthly/yearly repeats, uses Calendar-based calculation to handle variable month
     * lengths and leap years. For fixed intervals, adds repeatMs to the current time.
     */
    private fun rescheduleNext(
        context: Context,
        id: String,
        title: String,
        body: String,
        sound: Boolean,
        channelId: String,
        repeatMs: Long,
        repeatType: String?,
        dataJson: String?,
        subtitle: String?,
        imageUrl: String?,
        bigText: String?,
        actionsJson: String?
    ) {
        // For calendar-based repeats (monthly/yearly), use Calendar to compute
        // the next trigger. For fixed intervals, simply add repeatMs.
        val now = System.currentTimeMillis()
        val nextTriggerMs = if (repeatType == "monthly" || repeatType == "yearly") {
            LocalNotificationsFunctions.calculateNextTrigger(repeatType, now)
        } else {
            now + repeatMs
        }

        val rescheduleIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.ikromjon.localnotifications.NOTIFY"
            putExtra("notification_id", id)
            putExtra("title", title)
            putExtra("body", body)
            putExtra("sound", sound)
            putExtra("channel_id", channelId)
            putExtra("repeat_ms", repeatMs)
            if (repeatType != null) putExtra("repeat_type", repeatType)
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
            nextTriggerMs,
            pendingIntent
        )

        // Update the stored trigger time for boot restoration and getPending()
        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val infoJson = prefs.getString("notification_$id", null)
        if (infoJson != null) {
            val info = JSONObject(infoJson)
            info.put("triggerTimeMs", nextTriggerMs)
            prefs.edit().putString("notification_$id", info.toString()).apply()
        }

        Log.d(TAG, "Rescheduled repeating notification: $id, next in ${repeatMs / 1000}s")
    }

    /**
     * Downloads an image from a URL. Returns null on failure.
     */
    private fun downloadImage(urlString: String): Bitmap? {
        return try {
            val url = URL(urlString)
            val connection = url.openConnection() as HttpURLConnection
            connection.connectTimeout = 10_000
            connection.readTimeout = 10_000
            connection.doInput = true
            connection.connect()
            val inputStream = connection.inputStream
            val bitmap = BitmapFactory.decodeStream(inputStream)
            inputStream.close()
            connection.disconnect()
            bitmap
        } catch (e: Exception) {
            Log.e(TAG, "Failed to download image: ${e.message}")
            null
        }
    }
}
