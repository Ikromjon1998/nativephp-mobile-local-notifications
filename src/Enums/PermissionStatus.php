<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Enums;

enum PermissionStatus: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case NotDetermined = 'not_determined';
}
