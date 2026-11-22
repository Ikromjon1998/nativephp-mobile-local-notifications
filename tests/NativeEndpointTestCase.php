<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Tests;

use Composer\InstalledVersions;
use Ikromjon\LocalNotifications\LocalNotificationsServiceProvider;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

/**
 * Posts to /_native/api/events through the installed nativephp/mobile's own
 * routes file and controller, the way the native shell delivers an event.
 *
 * Native\Mobile\NativeServiceProvider is deliberately not booted: it defines
 * the global nativephp_call() and nativephp_can() Jump bridge functions for
 * the rest of the process, which would take the place of the stub the unit
 * tests install.
 */
abstract class NativeEndpointTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LocalNotificationsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // nativephp/mobile 4.6.1+ looks up pending callbacks, and 3.3.12/4.8.0
        // record signature nonces, in the cache. The app gets the file store
        // on device; the array store keeps these tests self-contained.
        $app['config']->set('cache.default', 'array');
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        // NativeServiceProvider registers this file with no prefix and no
        // middleware (spatie/laravel-package-tools ->hasRoute('api')).
        $router->group([], self::coreRoutesFile());
    }

    public static function coreRoutesFile(): string
    {
        $file = realpath(InstalledVersions::getInstallPath('nativephp/mobile').'/routes/api.php');

        if ($file === false) {
            throw new RuntimeException('nativephp/mobile is not installed with its routes/api.php.');
        }

        return $file;
    }
}
