<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Tests;

use Ikromjon\LocalNotifications\LocalNotificationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LocalNotificationsServiceProvider::class,
        ];
    }
}
