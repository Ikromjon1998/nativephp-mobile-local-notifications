<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Enums;

enum NotificationPriority: string
{
    case Low = 'low';
    case Default = 'default';
    case High = 'high';
    case Urgent = 'urgent';
}
