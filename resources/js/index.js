const baseUrl = '/_native/api/call';

/**
 * Call a native bridge function.
 * Matches the official NativePHP BridgeCall contract: includes CSRF token,
 * throws on error, and returns the unwrapped `data` property.
 *
 * @param {string} method - Bridge function name (e.g. 'LocalNotifications.Schedule')
 * @param {Object} params - Parameters to pass to the bridge function
 * @returns {Promise<Object>} The response data from the native bridge
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ method, params }),
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data;
}

// ---------------------------------------------------------------------------
// Bridge Functions
// ---------------------------------------------------------------------------

/**
 * Schedule a local notification.
 *
 * @param {Object} options
 * @param {string} options.id - Unique identifier
 * @param {string} options.title - Notification title
 * @param {string} options.body - Notification body text
 * @param {number} [options.delay] - Delay in seconds from now
 * @param {number} [options.at] - Unix timestamp to fire at
 * @param {string} [options.repeat] - Repeat interval: 'minute', 'hourly', 'daily', 'weekly', 'monthly', 'yearly'
 * @param {number} [options.repeatIntervalSeconds] - Custom repeat interval in seconds (min 60)
 * @param {number[]} [options.repeatDays] - Days of week (1=Mon..7=Sun). Requires `at`
 * @param {number} [options.repeatCount] - Limit repetitions (min 1)
 * @param {boolean} [options.sound=true] - Play sound
 * @param {number} [options.badge] - Badge number on app icon
 * @param {Object} [options.data] - Custom data payload
 * @param {string} [options.subtitle] - Subtitle text
 * @param {string} [options.image] - Image URL (http/https only)
 * @param {string} [options.bigText] - Expanded body text
 * @param {Array<{id: string, title: string, destructive?: boolean, input?: boolean}>} [options.actions] - Action buttons (max 3)
 * @returns {Promise<{success: boolean, id?: string, error?: string}>}
 */
export async function schedule(options = {}) {
    return bridgeCall('LocalNotifications.Schedule', options);
}

/**
 * Cancel a scheduled notification by its identifier.
 *
 * @param {string} id - The notification identifier to cancel
 * @returns {Promise<{success: boolean, id?: string, error?: string}>}
 */
export async function cancel(id) {
    return bridgeCall('LocalNotifications.Cancel', { id });
}

/**
 * Cancel all scheduled notifications.
 *
 * @returns {Promise<{success: boolean, error?: string}>}
 */
export async function cancelAll() {
    return bridgeCall('LocalNotifications.CancelAll');
}

/**
 * Get all pending scheduled notifications.
 *
 * @returns {Promise<{success: boolean, notifications?: string, count?: number, error?: string}>}
 */
export async function getPending() {
    return bridgeCall('LocalNotifications.GetPending');
}

/**
 * Request notification permission (Android 13+, iOS).
 *
 * @returns {Promise<{granted: boolean, status?: string, error?: string}>}
 */
export async function requestPermission() {
    return bridgeCall('LocalNotifications.RequestPermission');
}

/**
 * Check current notification permission status.
 *
 * @returns {Promise<{status: string, error?: string}>}
 */
export async function checkPermission() {
    return bridgeCall('LocalNotifications.CheckPermission');
}

/**
 * Update an existing scheduled notification.
 *
 * @param {string} id - The notification identifier to update
 * @param {Object} options - Properties to update (same as schedule, but id is separate)
 * @param {string} [options.title] - Updated title
 * @param {string} [options.body] - Updated body text
 * @param {number} [options.delay] - New delay in seconds from now
 * @param {number} [options.at] - New Unix timestamp to fire at
 * @param {string} [options.repeat] - New repeat interval
 * @param {number} [options.repeatIntervalSeconds] - New custom repeat interval
 * @param {number[]} [options.repeatDays] - New days of week
 * @param {number} [options.repeatCount] - New repetition limit
 * @param {boolean} [options.sound] - Play sound
 * @param {number} [options.badge] - Badge number
 * @param {Object} [options.data] - Custom data payload
 * @param {string} [options.subtitle] - Subtitle text
 * @param {string} [options.image] - Image URL
 * @param {string} [options.bigText] - Expanded body text
 * @param {Array<{id: string, title: string, destructive?: boolean, input?: boolean}>} [options.actions] - Action buttons
 * @returns {Promise<{success: boolean, id?: string, error?: string}>}
 */
export async function update(id, options = {}) {
    return bridgeCall('LocalNotifications.Update', { id, ...options });
}

// ---------------------------------------------------------------------------
// Event Constants
// ---------------------------------------------------------------------------

/**
 * Event name constants for use with the NativePHP `On()` listener.
 *
 * @example
 * import { On } from '#nativephp';
 * import { Events } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';
 *
 * On(Events.NotificationTapped, (payload) => {
 *     console.log('Tapped:', payload.id);
 * });
 */
export const Events = {
    NotificationScheduled: 'Ikromjon\\LocalNotifications\\Events\\NotificationScheduled',
    NotificationReceived: 'Ikromjon\\LocalNotifications\\Events\\NotificationReceived',
    NotificationTapped: 'Ikromjon\\LocalNotifications\\Events\\NotificationTapped',
    NotificationActionPressed: 'Ikromjon\\LocalNotifications\\Events\\NotificationActionPressed',
    PermissionGranted: 'Ikromjon\\LocalNotifications\\Events\\PermissionGranted',
    PermissionDenied: 'Ikromjon\\LocalNotifications\\Events\\PermissionDenied',
    NotificationUpdated: 'Ikromjon\\LocalNotifications\\Events\\NotificationUpdated',
};
