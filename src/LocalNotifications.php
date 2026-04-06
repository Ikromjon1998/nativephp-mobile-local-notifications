<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\BridgeFunction;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\Support\Config;
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

        return $this->call(BridgeFunction::Schedule, $data);
    }

    /**
     * Cancel a scheduled notification by its identifier.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->call(BridgeFunction::Cancel, ['id' => $id]);
    }

    /**
     * Cancel all scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function cancelAll(): array
    {
        return $this->call(BridgeFunction::CancelAll);
    }

    /**
     * Get a list of all pending scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->call(BridgeFunction::GetPending);
    }

    /**
     * Request permission to show notifications.
     *
     * @return array<string, mixed>
     */
    public function requestPermission(): array
    {
        return $this->call(BridgeFunction::RequestPermission);
    }

    /**
     * Check current notification permission status.
     *
     * @return array<string, mixed>
     */
    public function checkPermission(): array
    {
        return $this->call(BridgeFunction::CheckPermission);
    }

    /**
     * Update an existing scheduled notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $id, NotificationOptions|array $options): array
    {
        $data = $options instanceof NotificationOptions
            ? $options->toArray()
            : $this->normalizeOptions($options);

        $data['id'] = $id;

        return $this->call(BridgeFunction::Update, $data);
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
            'channel_id' => Config::get('channel_id', 'nativephp_local_notifications'),
            'channel_name' => Config::get('channel_name', 'Local Notifications'),
            'channel_description' => Config::get('channel_description', 'Notifications scheduled by the app'),
            'max_actions' => Config::get('max_actions', 3),
            'default_sound' => Config::get('default_sound', true),
            'tap_detection_delay_ms' => Config::get('tap_detection_delay_ms', 500),
            'navigation_replay_duration_ms' => Config::get('navigation_replay_duration_ms', 15000),
        ];
    }

    /**
     * Make a bridge call to the native layer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function call(BridgeFunction $function, array $data = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        // Inject config on every bridge call so native code can apply
        // settings (e.g. tap detection delay) before the first schedule().
        $data['_config'] = $this->nativeConfig();

        $result = nativephp_call($function->value, json_encode($data));

        if (! $result) {
            return [];
        }

        return json_decode($result, true) ?? [];
    }
}
