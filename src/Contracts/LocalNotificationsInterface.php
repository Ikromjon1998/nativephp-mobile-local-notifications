<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Contracts;

use Ikromjon\LocalNotifications\Data\NotificationOptions;

interface LocalNotificationsInterface
{
    /**
     * Schedule a local notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function schedule(NotificationOptions|array $options): array;

    /**
     * Cancel a scheduled notification by its identifier.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id): array;

    /**
     * Cancel all scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function cancelAll(): array;

    /**
     * Get a list of all pending scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function getPending(): array;

    /**
     * Request permission to show notifications.
     *
     * @return array<string, mixed>
     */
    public function requestPermission(): array;

    /**
     * Check current notification permission status.
     *
     * @return array<string, mixed>
     */
    public function checkPermission(): array;

    /**
     * Update an existing scheduled notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $id, NotificationOptions|array $options): array;
}
