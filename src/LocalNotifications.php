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
     *     data?: array,
     * }  $options
     */
    public function schedule(array $options): array
    {
        return $this->call('LocalNotifications.Schedule', $options);
    }

    /**
     * Cancel a scheduled notification by its identifier.
     */
    public function cancel(string $id): array
    {
        return $this->call('LocalNotifications.Cancel', ['id' => $id]);
    }

    /**
     * Cancel all scheduled notifications.
     */
    public function cancelAll(): array
    {
        return $this->call('LocalNotifications.CancelAll');
    }

    /**
     * Get a list of all pending scheduled notifications.
     */
    public function getPending(): array
    {
        return $this->call('LocalNotifications.GetPending');
    }

    /**
     * Request permission to show notifications.
     */
    public function requestPermission(): array
    {
        return $this->call('LocalNotifications.RequestPermission');
    }

    /**
     * Check current notification permission status.
     */
    public function checkPermission(): array
    {
        return $this->call('LocalNotifications.CheckPermission');
    }

    /**
     * Make a bridge call to the native layer.
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
