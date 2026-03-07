<?php

namespace Ikromjon\LocalNotifications;

use Illuminate\Support\ServiceProvider;

class LocalNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalNotifications::class, function () {
            return new LocalNotifications;
        });
    }
}
