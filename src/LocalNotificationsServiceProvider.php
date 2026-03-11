<?php

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Illuminate\Support\ServiceProvider;

class LocalNotificationsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(LocalNotificationsInterface::class, fn (): LocalNotifications => new LocalNotifications);
        $this->app->alias(LocalNotificationsInterface::class, LocalNotifications::class);
    }
}
