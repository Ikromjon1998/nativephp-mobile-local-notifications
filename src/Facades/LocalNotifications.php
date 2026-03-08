<?php

namespace Ikromjon\LocalNotifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array schedule(array $options)
 * @method static array cancel(string $id)
 * @method static array cancelAll()
 * @method static array getPending()
 * @method static array requestPermission()
 * @method static array checkPermission()
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
