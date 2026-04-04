<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Facades;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> schedule(NotificationOptions|array<string, mixed> $options)
 * @method static array<string, mixed> cancel(string $id)
 * @method static array<string, mixed> cancelAll()
 * @method static array<string, mixed> getPending()
 * @method static array<string, mixed> requestPermission()
 * @method static array<string, mixed> checkPermission()
 * @method static array<string, mixed> update(string $id, NotificationOptions|array<string, mixed> $options)
 *
 * @see \Ikromjon\LocalNotifications\LocalNotifications
 */
class LocalNotifications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LocalNotificationsInterface::class;
    }
}
