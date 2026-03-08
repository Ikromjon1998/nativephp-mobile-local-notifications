package com.ikromjon.localnotifications

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import org.json.JSONObject

/**
 * BroadcastReceiver that handles notification tap events.
 * Dispatches the NotificationTapped event to the PHP layer and then opens the app.
 */
class NotificationTapReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "NotifTapReceiver"
    }

    override fun onReceive(context: Context, intent: Intent) {
        val id = intent.getStringExtra("notification_id") ?: return
        val title = intent.getStringExtra("notification_title") ?: return
        val body = intent.getStringExtra("notification_body") ?: return
        val dataJson = intent.getStringExtra("notification_data")

        Log.d(TAG, "Notification tapped: $id")

        // Build the event payload
        val payload = JSONObject().apply {
            put("id", id)
            put("title", title)
            put("body", body)
            if (dataJson != null) {
                put("data", JSONObject(dataJson))
            }
        }

        // Try to dispatch the event immediately if the app is active
        val activity = LocalNotificationsFunctions.ActivityHolder.get()
        if (activity != null) {
            LocalNotificationsFunctions.dispatchEvent(
                activity,
                "Ikromjon\\LocalNotifications\\Events\\NotificationTapped",
                payload.toString()
            )
        } else {
            // App is not active — store the event for dispatch when the bridge becomes available
            LocalNotificationsFunctions.storePendingEvent(
                context,
                "Ikromjon\\LocalNotifications\\Events\\NotificationTapped",
                payload
            )
        }

        // Launch the app
        val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)?.apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }

        if (launchIntent != null) {
            context.startActivity(launchIntent)
        } else {
            Log.e(TAG, "Could not find launch intent for package: ${context.packageName}")
        }
    }
}
