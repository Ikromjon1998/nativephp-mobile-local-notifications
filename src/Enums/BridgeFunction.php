<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Enums;

enum BridgeFunction: string
{
    case Schedule = 'LocalNotifications.Schedule';
    case Cancel = 'LocalNotifications.Cancel';
    case CancelAll = 'LocalNotifications.CancelAll';
    case GetPending = 'LocalNotifications.GetPending';
    case RequestPermission = 'LocalNotifications.RequestPermission';
    case CheckPermission = 'LocalNotifications.CheckPermission';
    case Update = 'LocalNotifications.Update';
}
