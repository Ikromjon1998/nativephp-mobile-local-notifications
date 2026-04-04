<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Illuminate\Support\ServiceProvider;

class LocalNotificationsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/local-notifications.php',
            'local-notifications',
        );

        $this->app->singleton(LocalNotificationsInterface::class, fn (): LocalNotifications => new LocalNotifications);
        $this->app->alias(LocalNotificationsInterface::class, LocalNotifications::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'local-notifications');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/local-notifications.php' => config_path('local-notifications.php'),
            ], 'local-notifications-config');
        }
    }
}
