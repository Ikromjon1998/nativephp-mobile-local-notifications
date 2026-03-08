<?php

use Ikromjon\LocalNotifications\LocalNotifications;

it('registers LocalNotifications as a singleton', function () {
    $instance1 = $this->app->make(LocalNotifications::class);
    $instance2 = $this->app->make(LocalNotifications::class);

    expect($instance1)->toBeInstanceOf(LocalNotifications::class)
        ->and($instance1)->toBe($instance2);
});

it('resolves LocalNotifications from the container', function () {
    $instance = $this->app->make(LocalNotifications::class);

    expect($instance)->toBeInstanceOf(LocalNotifications::class);
});
