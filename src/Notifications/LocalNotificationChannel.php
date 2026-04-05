<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Notifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Illuminate\Notifications\Notification;

/** @see HasLocalNotification */
class LocalNotificationChannel
{
    public function __construct(
        protected LocalNotificationsInterface $localNotifications,
    ) {}

    /**
     * Send the given notification.
     *
     * @param  Notification&HasLocalNotification  $notification
     * @return array<string, mixed>
     */
    public function send(object $notifiable, Notification $notification): array
    {
        $message = $notification->toLocalNotification($notifiable);

        return $this->localNotifications->schedule($message->toArray());
    }
}
