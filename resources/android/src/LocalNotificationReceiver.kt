package com.nativephp.localnotifications

import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.media.AudioAttributes
import android.net.Uri
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
        // Handle notification dismiss (user swiped away): clear stored tap payload
        if (intent.action == "com.nativephp.localnotifications.DISMISS") {
            val dismissId = intent.getStringExtra("notification_id") ?: return
            LocalNotificationsFunctions.clearTapPayload(context, dismissId)
            return
        }

        val id = intent.getStringExtra("notification_id") ?: return
        val title = intent.getStringExtra("title") ?: return
        val body = intent.getStringExtra("body") ?: return

        // Extend the BroadcastReceiver lifetime so image downloads
        // and rescheduling can complete without the system killing us.
        val pendingResult = goAsync()
        try {
        val sound = intent.getBooleanExtra("sound", true)
        val soundName = intent.getStringExtra("sound_name")
        val baseChannelId = intent.getStringExtra("channel_id") ?: "nativephp_local_notifications"
        val dataJson = intent.getStringExtra("data")
        val subtitle = intent.getStringExtra("subtitle")
        val imageUrl = intent.getStringExtra("image")
        val bigText = intent.getStringExtra("big_text")
        val actionsJson = intent.getStringExtra("actions")

        Log.d(TAG, "Notification received: $id - $title, actionsJson=${actionsJson != null} (${actionsJson?.length ?: 0} chars)")

        // Build the notification
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Resolve the effective channel: use a per-sound channel for custom sounds
        val channelId = if (soundName != null) {
            ensureSoundChannel(context, notificationManager, baseChannelId, soundName)
        } else {
            baseChannelId
        }

        // Launch the app directly when the user taps the notification.
        // Using PendingIntent.getActivity() instead of getBroadcast() because
        // startActivity() from a BroadcastReceiver is restricted on Android 12+ (API 31).
        val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)?.apply {
            action = "com.nativephp.localnotifications.TAP"
            putExtra("notification_id", id)
            putExtra("notification_title", title)
            putExtra("notification_body", body)
            if (dataJson != null) putExtra("notification_data", dataJson)
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }

        val pendingIntent = if (launchIntent != null) {
            PendingIntent.getActivity(
                context,
                id.hashCode(),
                launchIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        } else {
            Log.e(TAG, "Could not resolve launch intent for package: ${context.packageName}")
            // Fallback: use a broadcast so the notification still works
            val fallbackIntent = Intent(context, NotificationTapReceiver::class.java).apply {
                this.action = "com.nativephp.localnotifications.TAP"
                putExtra("notification_id", id)
                putExtra("notification_title", title)
                putExtra("notification_body", body)
                if (dataJson != null) putExtra("notification_data", dataJson)
            }
            PendingIntent.getBroadcast(
                context,
                id.hashCode(),
                fallbackIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }

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
        } else if (actionsJson != null) {
            // BigTextStyle required for action buttons to appear on Samsung One UI.
            // Without an explicit expanded style, Samsung hides action buttons.
            builder.setStyle(NotificationCompat.BigTextStyle().bigText(body))
        }

        // Add action buttons if provided
        if (actionsJson != null) {
            Log.d(TAG, "Actions JSON for $id: $actionsJson")
            try {
                val actions = JSONArray(actionsJson)
                Log.d(TAG, "Parsed ${actions.length()} actions for notification $id")
                for (i in 0 until minOf(actions.length(), LocalNotificationsFunctions.maxActions)) {
                    val action = actions.getJSONObject(i)
                    val actionId = action.getString("id")
                    val actionTitle = action.getString("title")
                    val isInput = action.optBoolean("input", false)

                    val snoozeSecs = action.optInt("snooze", 0)

                    val actionIntent = Intent(context, NotificationActionReceiver::class.java).apply {
                        this.action = "com.nativephp.localnotifications.ACTION"
                        putExtra("notification_id", id)
                        putExtra("action_id", actionId)
                        if (dataJson != null) putExtra("notification_data", dataJson)
                        if (snoozeSecs > 0) {
                            putExtra("snooze_seconds", snoozeSecs)
                            putExtra("title", title)
                            putExtra("body", body)
                            putExtra("sound", sound)
                            if (soundName != null) putExtra("sound_name", soundName)
                            putExtra("channel_id", baseChannelId)
                            if (subtitle != null) putExtra("subtitle", subtitle)
                            if (imageUrl != null) putExtra("image", imageUrl)
                            if (bigText != null) putExtra("big_text", bigText)
                            if (actionsJson != null) putExtra("actions", actionsJson)
                        }
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

        // Set deleteIntent: fires when user swipes away the notification.
        // Clears the stored tap payload so we don't dispatch a false NotificationTapped.
        // Does NOT fire on auto-cancel (tap), so the payload persists for tap detection.
        val dismissIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.nativephp.localnotifications.DISMISS"
            putExtra("notification_id", id)
        }
        val dismissPendingIntent = PendingIntent.getBroadcast(
            context,
            ("dismiss_$id").hashCode(),
            dismissIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        builder.setDeleteIntent(dismissPendingIntent)

        // Store tap payload for warm-start detection.
        // On tap (auto-cancel), this persists. On dismiss (swipe), deleteIntent clears it.
        LocalNotificationsFunctions.storeTapPayload(context, id, title, body, dataJson)

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
        val remainingCount = if (intent.hasExtra("remaining_count")) intent.getIntExtra("remaining_count", 0) else -1
        if (repeatMs == 0L) {
            // Clean up non-repeating notifications from storage
            val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
            val ids = prefs.getStringSet("notification_ids", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            ids.remove(id)
            prefs.edit()
                .putStringSet("notification_ids", ids)
                .remove("notification_$id")
                .apply()
        } else if (remainingCount == 1) {
            // Last repetition reached — do not reschedule, clean up
            Log.d(TAG, "Repeat count exhausted for: $id")
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
            val nextCount = if (remainingCount > 1) remainingCount - 1 else -1
            rescheduleNext(context, id, title, body, sound, soundName, baseChannelId, repeatMs, repeatType, dataJson, subtitle, imageUrl, bigText, actionsJson, nextCount)
        }
        } finally {
            pendingResult.finish()
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
        soundName: String?,
        channelId: String,
        repeatMs: Long,
        repeatType: String?,
        dataJson: String?,
        subtitle: String?,
        imageUrl: String?,
        bigText: String?,
        actionsJson: String?,
        remainingCount: Int = -1
    ) {
        // For calendar-based repeats (monthly/yearly), use Calendar to compute
        // the next trigger. For fixed intervals, simply add repeatMs.
        val now = System.currentTimeMillis()
        val nextTriggerMs = if (repeatType == "monthly" || repeatType == "yearly") {
            NotificationScheduler.calculateNextTrigger(repeatType, now)
        } else {
            now + repeatMs
        }

        val rescheduleIntent = Intent(context, LocalNotificationReceiver::class.java).apply {
            action = "com.nativephp.localnotifications.NOTIFY"
            putExtra("notification_id", id)
            putExtra("title", title)
            putExtra("body", body)
            putExtra("sound", sound)
            if (soundName != null) putExtra("sound_name", soundName)
            putExtra("channel_id", channelId)
            putExtra("repeat_ms", repeatMs)
            if (repeatType != null) putExtra("repeat_type", repeatType)
            if (remainingCount > 0) putExtra("remaining_count", remainingCount)
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

        // Update the stored trigger time and remaining count for boot restoration and getPending()
        val prefs = context.getSharedPreferences(LocalNotificationsFunctions.PREFS_NAME, Context.MODE_PRIVATE)
        val infoJson = prefs.getString("notification_$id", null)
        if (infoJson != null) {
            val info = JSONObject(infoJson)
            info.put("triggerTimeMs", nextTriggerMs)
            if (remainingCount > 0) {
                info.put("remainingCount", remainingCount)
            } else {
                info.remove("remainingCount")
            }
            prefs.edit().putString("notification_$id", info.toString()).apply()
        }

        Log.d(TAG, "Rescheduled repeating notification: $id, next in ${repeatMs / 1000}s")
    }

    /**
     * Create (or re-use) a notification channel with a custom sound.
     * Android O+ requires sound to be set on the channel, not the notification builder.
     * Channel ID format: {baseChannelId}_sound_{name} (extension stripped).
     */
    private fun ensureSoundChannel(
        context: Context,
        manager: NotificationManager,
        baseChannelId: String,
        soundName: String
    ): String {
        val name = soundName.substringBeforeLast(".")
        val soundChannelId = "${baseChannelId}_sound_$name"

        // Channel already exists — no-op (Android ignores re-creation)
        val resId = context.resources.getIdentifier(name, "raw", context.packageName)
        if (resId == 0) {
            Log.w(TAG, "Custom sound resource not found: $name (res/raw/$name). Falling back to default channel.")
            return baseChannelId
        }

        val soundUri = Uri.parse("android.resource://${context.packageName}/raw/$name")
        val audioAttributes = AudioAttributes.Builder()
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .build()

        val channel = NotificationChannel(
            soundChannelId,
            "Notifications ($name)",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Notifications with custom sound: $name"
            setSound(soundUri, audioAttributes)
            enableVibration(true)
        }
        manager.createNotificationChannel(channel)

        return soundChannelId
    }

    /**
     * Downloads an image from a URL. Returns null on failure.
     * Only allows http:// and https:// schemes to prevent SSRF via file:// or other schemes.
     */
    private fun downloadImage(urlString: String): Bitmap? {
        return try {
            val url = URL(urlString)
            val scheme = url.protocol.lowercase()
            if (scheme != "http" && scheme != "https") {
                Log.w(TAG, "Rejected image URL with unsupported scheme: $scheme")
                return null
            }
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
