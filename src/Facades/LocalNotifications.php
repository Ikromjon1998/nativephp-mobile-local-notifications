<?php

namespace Ikromjon\LocalNotifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> schedule(array<string, mixed> $options)
 * @method static array<string, mixed> cancel(string $id)
 * @method static array<string, mixed> cancelAll()
 * @method static array<string, mixed> getPending()
 * @method static array<string, mixed> requestPermission()
 * @method static array<string, mixed> checkPermission()
 *
 * @see \Ikromjon\LocalNotifications\LocalNotifications
 */
class LocalNotifications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ikromjon\LocalNotifications\LocalNotifications::class;
    }
}
