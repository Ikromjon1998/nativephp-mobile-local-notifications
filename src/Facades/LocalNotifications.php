<?php

namespace Ikromjon\LocalNotifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed schedule(array $options)
 * @method static mixed cancel(string $id)
 * @method static mixed cancelAll()
 * @method static mixed getPending()
 * @method static mixed requestPermission()
 * @method static mixed checkPermission()
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
