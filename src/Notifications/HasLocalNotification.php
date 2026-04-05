<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Notifications;

interface HasLocalNotification
{
    public function toLocalNotification(object $notifiable): LocalNotificationMessage;
}
