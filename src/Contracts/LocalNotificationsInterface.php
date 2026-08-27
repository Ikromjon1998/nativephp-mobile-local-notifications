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
     * @param  bool  $critical  Also request iOS critical-alert authorization
     *                          (requires the critical-alerts entitlement; ignored on Android)
     * @return array<string, mixed>
     */
    public function requestPermission(bool $critical = false): array;

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

    /**
     * Register a payload transformer.
     *
     * The callback receives the notification payload immediately before it is
     * dispatched to the native layer and returns the payload to send. It runs
     * for schedule() and update() only, in registration order, and its output
     * is re-validated. The primary use is localization.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $transformer
     */
    public function transformUsing(callable $transformer): self;

    /**
     * Remove all registered payload transformers.
     */
    public function flushTransformers(): self;
}
