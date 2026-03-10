<?php

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

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

        if (isset($options['repeat'], $options['repeatIntervalSeconds'])) {
            throw new \InvalidArgumentException(
                'Cannot use both "repeat" and "repeatIntervalSeconds". Choose one.',
            );
        }

        if (isset($options['repeatIntervalSeconds']) && $options['repeatIntervalSeconds'] < 60) {
            throw new \InvalidArgumentException(
                'repeatIntervalSeconds must be at least 60 seconds.',
            );
        }

        if (isset($options['repeatDays'])) {
            if (isset($options['repeat']) || isset($options['repeatIntervalSeconds'])) {
                throw new \InvalidArgumentException(
                    'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds".',
                );
            }

            if (! isset($options['at'])) {
                throw new \InvalidArgumentException(
                    '"repeatDays" requires "at" to determine the time of day.',
                );
            }
        }

        return $options;
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
