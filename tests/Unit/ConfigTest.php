<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\LocalNotifications;

describe('config defaults', function (): void {
    it('has all expected config keys with defaults', function (): void {
        expect(config('local-notifications.channel_id'))->toBe('nativephp_local_notifications')
            ->and(config('local-notifications.channel_name'))->toBe('Local Notifications')
            ->and(config('local-notifications.channel_description'))->toBe('Notifications scheduled by the app')
            ->and(config('local-notifications.max_actions'))->toBe(3)
            ->and(config('local-notifications.min_repeat_interval_seconds'))->toBe(60)
            ->and(config('local-notifications.default_sound'))->toBeTrue()
            ->and(config('local-notifications.tap_detection_delay_ms'))->toBe(500)
            ->and(config('local-notifications.navigation_replay_duration_ms'))->toBe(15000);
    });
});

describe('config overrides', function (): void {
    it('allows overriding channel_id', function (): void {
        config()->set('local-notifications.channel_id', 'my_custom_channel');

        expect(config('local-notifications.channel_id'))->toBe('my_custom_channel');
    });

    it('allows overriding channel_name', function (): void {
        config()->set('local-notifications.channel_name', 'My App Alerts');

        expect(config('local-notifications.channel_name'))->toBe('My App Alerts');
    });

    it('allows overriding max_actions', function (): void {
        config()->set('local-notifications.max_actions', 2);

        expect(config('local-notifications.max_actions'))->toBe(2);
    });

    it('allows overriding min_repeat_interval_seconds', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        expect(config('local-notifications.min_repeat_interval_seconds'))->toBe(120);
    });

    it('allows overriding default_sound to false', function (): void {
        config()->set('local-notifications.default_sound', false);

        expect(config('local-notifications.default_sound'))->toBeFalse();
    });

    it('allows overriding tap_detection_delay_ms', function (): void {
        config()->set('local-notifications.tap_detection_delay_ms', 1000);

        expect(config('local-notifications.tap_detection_delay_ms'))->toBe(1000);
    });

    it('allows overriding navigation_replay_duration_ms', function (): void {
        config()->set('local-notifications.navigation_replay_duration_ms', 30000);

        expect(config('local-notifications.navigation_replay_duration_ms'))->toBe(30000);
    });
});

describe('config flows to bridge', function (): void {
    it('injects overridden channel_id into _config', function (): void {
        config()->set('local-notifications.channel_id', 'my_app_notifs');

        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['_config']['channel_id'])->toBe('my_app_notifs');
    });

    it('injects overridden max_actions into _config', function (): void {
        config()->set('local-notifications.max_actions', 2);

        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['_config']['max_actions'])->toBe(2);
    });

    it('injects overridden default_sound into _config', function (): void {
        config()->set('local-notifications.default_sound', false);

        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['_config']['default_sound'])->toBeFalse();
    });

    it('injects overridden tap_detection_delay_ms into _config', function (): void {
        config()->set('local-notifications.tap_detection_delay_ms', 1000);

        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['_config']['tap_detection_delay_ms'])->toBe(1000);
    });

    it('injects overridden navigation_replay_duration_ms into _config', function (): void {
        config()->set('local-notifications.navigation_replay_duration_ms', 30000);

        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['_config']['navigation_replay_duration_ms'])->toBe(30000);
    });

    it('injects _config into cancel calls', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->cancel('test-id');

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKey('tap_detection_delay_ms');
    });

    it('injects _config into cancelAll calls', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->cancelAll();

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKey('tap_detection_delay_ms');
    });

    it('injects _config into getPending calls', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        (new LocalNotifications)->getPending();

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKey('tap_detection_delay_ms');
    });

    it('injects _config into requestPermission calls', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['granted' => true]);
        });

        (new LocalNotifications)->requestPermission();

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKey('tap_detection_delay_ms');
    });

    it('injects _config into checkPermission calls', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['status' => 'granted']);
        });

        (new LocalNotifications)->checkPermission();

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKey('tap_detection_delay_ms');
    });
});

describe('interface contract', function (): void {
    it('implements LocalNotificationsInterface', function (): void {
        expect(new LocalNotifications)->toBeInstanceOf(LocalNotificationsInterface::class);
    });

    it('has all interface methods', function (): void {
        $reflection = new ReflectionClass(LocalNotifications::class);

        expect($reflection->hasMethod('schedule'))->toBeTrue()
            ->and($reflection->hasMethod('cancel'))->toBeTrue()
            ->and($reflection->hasMethod('cancelAll'))->toBeTrue()
            ->and($reflection->hasMethod('getPending'))->toBeTrue()
            ->and($reflection->hasMethod('requestPermission'))->toBeTrue()
            ->and($reflection->hasMethod('checkPermission'))->toBeTrue();
    });
});

describe('config validation integration', function (): void {
    it('uses custom min_repeat_interval_seconds for validation', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
            'repeatIntervalSeconds' => 90,
        ]);
    })->throws(InvalidArgumentException::class, 'at least 120 seconds');

    it('uses custom max_actions for validation', function (): void {
        config()->set('local-notifications.max_actions', 2);

        (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
                ['id' => 'a3', 'title' => 'A3'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 2 action buttons');

    it('passes with value at custom min_repeat_interval_seconds', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $result = (new LocalNotifications)->schedule([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
            'repeatIntervalSeconds' => 120,
        ]);

        expect($result)->toBe(['success' => true]);
    });
});
