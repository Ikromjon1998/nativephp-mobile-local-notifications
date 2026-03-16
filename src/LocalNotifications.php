<?php

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\Validation\NotificationValidator;

class LocalNotifications implements LocalNotificationsInterface
{
    /**
     * Schedule a local notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function schedule(NotificationOptions|array $options): array
    {
        $data = $options instanceof NotificationOptions
            ? $options->toArray()
            : $this->normalizeOptions($options);

        // Inject configurable values for the native layer
        $data['_config'] = $this->nativeConfig();

        return $this->call('LocalNotifications.Schedule', $data);
    }

    /**
     * Cancel a scheduled notification by its identifier.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->call('LocalNotifications.Cancel', ['id' => $id]);
    }

    /**
     * Cancel all scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function cancelAll(): array
    {
        return $this->call('LocalNotifications.CancelAll');
    }

    /**
     * Get a list of all pending scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->call('LocalNotifications.GetPending');
    }

    /**
     * Request permission to show notifications.
     *
     * @return array<string, mixed>
     */
    public function requestPermission(): array
    {
        return $this->call('LocalNotifications.RequestPermission');
    }

    /**
     * Check current notification permission status.
     *
     * @return array<string, mixed>
     */
    public function checkPermission(): array
    {
        return $this->call('LocalNotifications.CheckPermission');
    }

    /**
     * Normalize a raw options array, converting enum values to strings and validating.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    protected function normalizeOptions(array $options): array
    {
        if (isset($options['repeat']) && $options['repeat'] instanceof RepeatInterval) {
            $options['repeat'] = $options['repeat']->value;
        }

        NotificationValidator::validate($options);

        return $options;
    }

    /**
     * Build the config values to pass to the native layer.
     *
     * @return array<string, mixed>
     */
    protected function nativeConfig(): array
    {
        return [
            'channel_id' => config('local-notifications.channel_id', 'nativephp_local_notifications'),
            'channel_name' => config('local-notifications.channel_name', 'Local Notifications'),
            'channel_description' => config('local-notifications.channel_description', 'Notifications scheduled by the app'),
            'default_sound' => config('local-notifications.default_sound', true),
            'tap_detection_delay_ms' => config('local-notifications.tap_detection_delay_ms', 500),
            'navigation_replay_duration_ms' => config('local-notifications.navigation_replay_duration_ms', 15000),
        ];
    }

    /**
     * Make a bridge call to the native layer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function call(string $function, array $data = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        $result = nativephp_call($function, json_encode($data));

        if (! $result) {
            return [];
        }

        return json_decode($result, true) ?? [];
    }
}
