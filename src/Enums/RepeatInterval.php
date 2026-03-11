<?php

namespace Ikromjon\LocalNotifications\Enums;

enum RepeatInterval: string
{
    case Minute = 'minute';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
