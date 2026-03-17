<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Android Notification Channel
    |--------------------------------------------------------------------------
    |
    | The channel ID and name used for Android notifications. The channel is
    | created automatically with IMPORTANCE_HIGH. Users can override the
    | channel settings in their device's notification settings.
    |
    */

    'channel_id' => env('LOCAL_NOTIFICATIONS_CHANNEL_ID', 'nativephp_local_notifications'),

    'channel_name' => env('LOCAL_NOTIFICATIONS_CHANNEL_NAME', 'Local Notifications'),

    'channel_description' => env('LOCAL_NOTIFICATIONS_CHANNEL_DESCRIPTION', 'Notifications scheduled by the app'),

    /*
    |--------------------------------------------------------------------------
    | Action Buttons
    |--------------------------------------------------------------------------
    |
    | Maximum number of action buttons per notification. The default is 3,
    | which is the recommended limit for both platforms. Must be at least 1.
    | Both Android and iOS will truncate actions beyond this limit.
    |
    */

    'max_actions' => 3,

    /*
    |--------------------------------------------------------------------------
    | Repeat Interval Constraints
    |--------------------------------------------------------------------------
    |
    | Minimum custom repeat interval in seconds. Android and iOS may not
    | reliably deliver notifications faster than once per minute.
    |
    */

    'min_repeat_interval_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Default Sound
    |--------------------------------------------------------------------------
    |
    | Whether notifications play a sound by default when no explicit sound
    | parameter is provided.
    |
    */

    'default_sound' => true,

    /*
    |--------------------------------------------------------------------------
    | Tap Detection (Android)
    |--------------------------------------------------------------------------
    |
    | Warm-start tap detection delay in milliseconds. After the app resumes,
    | we wait this long before checking which notifications were tapped. This
    | gives the system time to process deleteIntent broadcasts for dismissed
    | notifications.
    |
    */

    'tap_detection_delay_ms' => 500,

    /*
    |--------------------------------------------------------------------------
    | Navigation Replay (Android)
    |--------------------------------------------------------------------------
    |
    | When using wire:navigate (SPA-like navigation), cold-start tap events
    | are replayed on each livewire:navigated event for this duration. After
    | the window expires, the replay listener removes itself.
    |
    */

    'navigation_replay_duration_ms' => 15000,

];
