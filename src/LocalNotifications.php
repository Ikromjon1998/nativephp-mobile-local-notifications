<?php

namespace Ikromjon\LocalNotifications;

use Native\Mobile\Bridge;

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
    public function schedule(array $options): mixed
    {
        return Bridge::call('LocalNotifications.Schedule', $options);
    }

    /**
     * Cancel a scheduled notification by its identifier.
     */
    public function cancel(string $id): mixed
    {
        return Bridge::call('LocalNotifications.Cancel', ['id' => $id]);
    }

    /**
     * Cancel all scheduled notifications.
     */
    public function cancelAll(): mixed
    {
        return Bridge::call('LocalNotifications.CancelAll');
    }

    /**
     * Get a list of all pending scheduled notifications.
     */
    public function getPending(): mixed
    {
        return Bridge::call('LocalNotifications.GetPending');
    }

    /**
     * Request permission to show notifications.
     */
    public function requestPermission(): mixed
    {
        return Bridge::call('LocalNotifications.RequestPermission');
    }

    /**
     * Check current notification permission status.
     */
    public function checkPermission(): mixed
    {
        return Bridge::call('LocalNotifications.CheckPermission');
    }
}
