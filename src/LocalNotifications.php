<?php

namespace Ikromjon\LocalNotifications;

class LocalNotifications
{
    /**
     * Schedule a local notification.
     *
     * @param  array{
     *     id: string,
     *     title: string,
     *     body: string,
     *     delay?: int,
     *     at?: int,
     *     repeat?: string,
     *     sound?: bool,
     *     badge?: int,
     *     data?: array<string, mixed>,
     *     subtitle?: string,
     *     image?: string,
     *     bigText?: string,
     * }  $options
     * @return array<string, mixed>
     */
    public function schedule(array $options): array
    {
        return $this->call('LocalNotifications.Schedule', $options);
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
     * Make a bridge call to the native layer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function call(string $function, array $data = []): array
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call($function, json_encode($data));
            if ($result) {
                return json_decode($result, true) ?? [];
            }
        }

        return [];
    }
}
