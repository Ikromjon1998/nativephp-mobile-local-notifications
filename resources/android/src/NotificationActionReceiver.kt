package com.ikromjon.localnotifications

import android.app.NotificationManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.app.RemoteInput
import org.json.JSONObject

/**
 * BroadcastReceiver that handles notification action button presses.
 * Dispatches the NotificationActionPressed event to the PHP layer.
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

        // Dismiss the notification
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.cancel(notificationId.hashCode())

        // Try to dispatch the event immediately if the app is active
        val activity = LocalNotificationsFunctions.ActivityHolder.get()
        if (activity != null) {
            LocalNotificationsFunctions.dispatchEvent(
                activity,
                "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed",
                payload.toString()
            )
        } else {
            // Store as pending event and launch the app
            LocalNotificationsFunctions.storePendingEvent(
                context,
                "Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed",
                payload
            )

            val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)?.apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            if (launchIntent != null) {
                context.startActivity(launchIntent)
            }
        }
    }
}
