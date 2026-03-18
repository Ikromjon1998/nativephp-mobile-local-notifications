<?php

use Ikromjon\LocalNotifications\LocalNotifications;

it('registers LocalNotifications as a singleton', function (): void {
    $instance1 = $this->app->make(LocalNotifications::class);
    $instance2 = $this->app->make(LocalNotifications::class);

    expect($instance1)->toBeInstanceOf(LocalNotifications::class)
        ->and($instance1)->toBe($instance2);
});

it('resolves LocalNotifications from the container', function (): void {
    $instance = $this->app->make(LocalNotifications::class);

    expect($instance)->toBeInstanceOf(LocalNotifications::class);
});

it('registers the local-notifications view namespace', function (): void {
    $hints = $this->app['view']->getFinder()->getHints();

    expect($hints)->toHaveKey('local-notifications');
});

it('renders the init blade component', function (): void {
    $html = $this->app['view']->make('local-notifications::components.init')->render();

    expect($html)
        ->toContain('LocalNotifications.CheckPermission')
        ->toContain('/_native/api/call')
        ->toContain('livewire:navigated');
});

it('merges default config values', function (): void {
    expect(config('local-notifications.channel_id'))->toBe('nativephp_local_notifications')
        ->and(config('local-notifications.channel_name'))->toBe('Local Notifications')
        ->and(config('local-notifications.max_actions'))->toBe(3)
        ->and(config('local-notifications.min_repeat_interval_seconds'))->toBe(60)
        ->and(config('local-notifications.default_sound'))->toBeTrue()
        ->and(config('local-notifications.tap_detection_delay_ms'))->toBe(500)
        ->and(config('local-notifications.navigation_replay_duration_ms'))->toBe(15000);
});
