<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\LocalNotifications;

beforeEach(function (): void {
    $this->notifications = new LocalNotifications;
});

describe('schedule', function (): void {
    it('calls the bridge with correct function name and options', function (): void {
        $capturedFunction = null;
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedFunction, &$capturedData) {
            $capturedFunction = $function;
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true, 'id' => 'test-1']);
        });

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test Title',
            'body' => 'Test Body',
            'delay' => 60,
        ]);

        unset($capturedData['_config']);
        expect($capturedFunction)->toBe('LocalNotifications.Schedule')
            ->and($capturedData)->toBe([
                'id' => 'test-1',
                'title' => 'Test Title',
                'body' => 'Test Body',
                'delay' => 60,
            ])
            ->and($result)->toBe(['success' => true, 'id' => 'test-1']);
    });

    it('passes all optional parameters to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'full-test',
            'title' => 'Full Test',
            'body' => 'All options',
            'delay' => 300,
            'at' => 1700000000,
            'repeat' => 'daily',
            'sound' => true,
            'badge' => 5,
            'data' => ['key' => 'value'],
            'subtitle' => 'A subtitle',
            'image' => 'https://example.com/image.jpg',
            'bigText' => 'This is a much longer body text for the expanded view.',
        ]);

        unset($capturedData['_config']);
        expect($capturedData)->toBe([
            'id' => 'full-test',
            'title' => 'Full Test',
            'body' => 'All options',
            'delay' => 300,
            'at' => 1700000000,
            'repeat' => 'daily',
            'sound' => true,
            'badge' => 5,
            'data' => ['key' => 'value'],
            'subtitle' => 'A subtitle',
            'image' => 'https://example.com/image.jpg',
            'bigText' => 'This is a much longer body text for the expanded view.',
        ]);
    });

    it('passes rich content parameters independently', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'image-only',
            'title' => 'Image Notification',
            'body' => 'Check this out',
            'image' => 'https://example.com/photo.png',
        ]);

        expect($capturedData['image'])->toBe('https://example.com/photo.png')
            ->and($capturedData)->not->toHaveKey('subtitle')
            ->and($capturedData)->not->toHaveKey('bigText');
    });

    it('passes subtitle without other rich content', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'subtitle-only',
            'title' => 'Subtitle Test',
            'body' => 'Body text',
            'subtitle' => 'My subtitle',
        ]);

        expect($capturedData['subtitle'])->toBe('My subtitle')
            ->and($capturedData)->not->toHaveKey('image')
            ->and($capturedData)->not->toHaveKey('bigText');
    });

    it('passes action buttons to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'actions-test',
            'title' => 'Actions Test',
            'body' => 'With actions',
            'delay' => 60,
            'actions' => [
                ['id' => 'reply', 'title' => 'Reply', 'input' => true],
                ['id' => 'snooze', 'title' => 'Snooze'],
                ['id' => 'delete', 'title' => 'Delete', 'destructive' => true],
            ],
        ]);

        expect($capturedData['actions'])->toHaveCount(3)
            ->and($capturedData['actions'][0])->toBe(['id' => 'reply', 'title' => 'Reply', 'input' => true])
            ->and($capturedData['actions'][1])->toBe(['id' => 'snooze', 'title' => 'Snooze'])
            ->and($capturedData['actions'][2])->toBe(['id' => 'delete', 'title' => 'Delete', 'destructive' => true]);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('returns empty array when bridge returns invalid json', function (): void {
        stubNativephpCall(fn (): string => 'not-json');

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('handles empty options array', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([]);

        unset($capturedData['_config']);
        expect($capturedData)->toBe([]);
    });

    it('preserves nested data arrays', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'nested',
            'title' => 'Nested',
            'body' => 'Body',
            'data' => [
                'user' => ['id' => 1, 'name' => 'John'],
                'tags' => ['urgent', 'work'],
            ],
        ]);

        expect($capturedData['data'])->toBe([
            'user' => ['id' => 1, 'name' => 'John'],
            'tags' => ['urgent', 'work'],
        ]);
    });

    it('converts RepeatInterval enum to string value', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'enum-test',
            'title' => 'Enum Test',
            'body' => 'Testing enum',
            'delay' => 60,
            'repeat' => RepeatInterval::Daily,
        ]);

        expect($capturedData['repeat'])->toBe('daily');
    });

    it('converts all RepeatInterval enum cases to correct strings', function (): void {
        $cases = [
            [RepeatInterval::Minute, 'minute'],
            [RepeatInterval::Hourly, 'hourly'],
            [RepeatInterval::Daily, 'daily'],
            [RepeatInterval::Weekly, 'weekly'],
            [RepeatInterval::Monthly, 'monthly'],
            [RepeatInterval::Yearly, 'yearly'],
        ];

        foreach ($cases as [$enum, $expected]) {
            $capturedData = null;

            stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
                $capturedData = json_decode($data, true);

                return json_encode(['success' => true]);
            });

            $this->notifications->schedule([
                'id' => "enum-$expected",
                'title' => 'Test',
                'body' => 'Body',
                'repeat' => $enum,
            ]);

            expect($capturedData['repeat'])->toBe($expected);
        }
    });

    it('converts Monthly enum to monthly string', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'monthly-test',
            'title' => 'Monthly Test',
            'body' => 'Monthly body',
            'at' => 1700000000,
            'repeat' => RepeatInterval::Monthly,
        ]);

        expect($capturedData['repeat'])->toBe('monthly');
    });

    it('converts Yearly enum to yearly string', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'yearly-test',
            'title' => 'Yearly Test',
            'body' => 'Yearly body',
            'at' => 1700000000,
            'repeat' => RepeatInterval::Yearly,
        ]);

        expect($capturedData['repeat'])->toBe('yearly');
    });

    it('passes repeatIntervalSeconds to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'custom-interval',
            'title' => 'Custom Interval',
            'body' => 'Every 2 hours',
            'repeatIntervalSeconds' => 7200,
        ]);

        expect($capturedData['repeatIntervalSeconds'])->toBe(7200)
            ->and($capturedData)->not->toHaveKey('repeat');
    });

    it('throws when both repeat and repeatIntervalSeconds are set', function (): void {
        $this->notifications->schedule([
            'id' => 'conflict',
            'title' => 'Conflict',
            'body' => 'Body',
            'repeat' => 'daily',
            'repeatIntervalSeconds' => 3600,
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use both "repeat" and "repeatIntervalSeconds"');

    it('throws when repeatIntervalSeconds is less than 60', function (): void {
        $this->notifications->schedule([
            'id' => 'too-short',
            'title' => 'Too Short',
            'body' => 'Body',
            'repeatIntervalSeconds' => 30,
        ]);
    })->throws(InvalidArgumentException::class, 'repeatIntervalSeconds must be at least 60 seconds');

    it('passes repeatDays to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'weekday-alarm',
            'title' => 'Weekday Alarm',
            'body' => 'Wake up!',
            'at' => 1700000000,
            'repeatDays' => [1, 2, 3, 4, 5],
        ]);

        expect($capturedData['repeatDays'])->toBe([1, 2, 3, 4, 5])
            ->and($capturedData['at'])->toBe(1700000000)
            ->and($capturedData)->not->toHaveKey('repeat');
    });

    it('throws when repeatDays is used with repeat', function (): void {
        $this->notifications->schedule([
            'id' => 'conflict',
            'title' => 'Conflict',
            'body' => 'Body',
            'at' => 1700000000,
            'repeat' => 'daily',
            'repeatDays' => [1, 3, 5],
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds"');

    it('throws when repeatDays is used without at', function (): void {
        $this->notifications->schedule([
            'id' => 'no-at',
            'title' => 'No At',
            'body' => 'Body',
            'repeatDays' => [1, 3, 5],
        ]);
    })->throws(InvalidArgumentException::class, '"repeatDays" requires "at" to determine the time of day');

    it('passes repeatCount to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'count-test',
            'title' => 'Count Test',
            'body' => 'Body',
            'repeat' => 'daily',
            'repeatCount' => 5,
        ]);

        expect($capturedData['repeatCount'])->toBe(5)
            ->and($capturedData['repeat'])->toBe('daily');
    });

    it('throws when repeatCount is less than 1', function (): void {
        $this->notifications->schedule([
            'id' => 'bad-count',
            'title' => 'Bad Count',
            'body' => 'Body',
            'repeat' => 'daily',
            'repeatCount' => 0,
        ]);
    })->throws(InvalidArgumentException::class, 'repeatCount must be at least 1');

    it('throws when repeatCount is used without repeat mechanism', function (): void {
        $this->notifications->schedule([
            'id' => 'no-repeat',
            'title' => 'No Repeat',
            'body' => 'Body',
            'repeatCount' => 3,
        ]);
    })->throws(InvalidArgumentException::class, '"repeatCount" requires a repeat mechanism');

    it('throws when more than max_actions action buttons are provided', function (): void {
        $this->notifications->schedule([
            'id' => 'too-many-actions',
            'title' => 'Too Many',
            'body' => 'Body',
            'actions' => [
                ['id' => 'a1', 'title' => 'Action 1'],
                ['id' => 'a2', 'title' => 'Action 2'],
                ['id' => 'a3', 'title' => 'Action 3'],
                ['id' => 'a4', 'title' => 'Action 4'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 3 action buttons');

    it('passes string repeat values unchanged', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'string-test',
            'title' => 'String Test',
            'body' => 'Testing string',
            'repeat' => 'weekly',
        ]);

        expect($capturedData['repeat'])->toBe('weekly');
    });

    it('returns empty array when bridge returns empty string', function (): void {
        stubNativephpCall(fn (): string => '');

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('passes zero delay to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'zero-delay',
            'title' => 'Test',
            'body' => 'Body',
            'delay' => 0,
        ]);

        expect($capturedData['delay'])->toBe(0);
    });

    it('passes sound false to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'silent',
            'title' => 'Test',
            'body' => 'Body',
            'sound' => false,
        ]);

        expect($capturedData['sound'])->toBeFalse();
    });

    it('passes badge zero to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'zero-badge',
            'title' => 'Test',
            'body' => 'Body',
            'badge' => 0,
        ]);

        expect($capturedData['badge'])->toBe(0);
    });

    it('injects _config with default values into schedule bridge call', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([
            'id' => 'config-test',
            'title' => 'Config Test',
            'body' => 'Body',
        ]);

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKeys([
                'channel_id',
                'channel_name',
                'channel_description',
                'max_actions',
                'default_sound',
                'tap_detection_delay_ms',
                'navigation_replay_duration_ms',
            ])
            ->and($capturedData['_config']['channel_id'])->toBe('nativephp_local_notifications')
            ->and($capturedData['_config']['max_actions'])->toBe(3)
            ->and($capturedData['_config']['default_sound'])->toBeTrue()
            ->and($capturedData['_config']['tap_detection_delay_ms'])->toBe(500)
            ->and($capturedData['_config']['navigation_replay_duration_ms'])->toBe(15000);
    });
});

describe('cancel', function (): void {
    it('calls the bridge with correct function name and id', function (): void {
        $capturedFunction = null;
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedFunction, &$capturedData) {
            $capturedFunction = $function;
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true, 'id' => 'cancel-me']);
        });

        $result = $this->notifications->cancel('cancel-me');

        unset($capturedData['_config']);
        expect($capturedFunction)->toBe('LocalNotifications.Cancel')
            ->and($capturedData)->toBe(['id' => 'cancel-me'])
            ->and($result)->toBe(['success' => true, 'id' => 'cancel-me']);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->cancel('test-id');

        expect($result)->toBe([]);
    });

    it('handles special characters in id', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->cancel('id-with-special/chars.and:colons');

        expect($capturedData['id'])->toBe('id-with-special/chars.and:colons');
    });
});

describe('cancelAll', function (): void {
    it('calls the bridge with correct function name and no data', function (): void {
        $capturedFunction = null;
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedFunction, &$capturedData) {
            $capturedFunction = $function;
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $result = $this->notifications->cancelAll();

        unset($capturedData['_config']);
        expect($capturedFunction)->toBe('LocalNotifications.CancelAll')
            ->and($capturedData)->toBe([])
            ->and($result)->toBe(['success' => true]);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->cancelAll();

        expect($result)->toBe([]);
    });
});

describe('getPending', function (): void {
    it('calls the bridge with correct function name', function (): void {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['success' => true, 'notifications' => '[]', 'count' => 0]);
        });

        $result = $this->notifications->getPending();

        expect($capturedFunction)->toBe('LocalNotifications.GetPending')
            ->and($result)->toBe(['success' => true, 'notifications' => '[]', 'count' => 0]);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->getPending();

        expect($result)->toBe([]);
    });
});

describe('requestPermission', function (): void {
    it('calls the bridge with correct function name', function (): void {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['granted' => true]);
        });

        $result = $this->notifications->requestPermission();

        expect($capturedFunction)->toBe('LocalNotifications.RequestPermission')
            ->and($result)->toBe(['granted' => true]);
    });

    it('handles denied permission response', function (): void {
        stubNativephpCall(fn () => json_encode(['granted' => false, 'status' => 'pending']));

        $result = $this->notifications->requestPermission();

        expect($result)->toBe(['granted' => false, 'status' => 'pending']);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->requestPermission();

        expect($result)->toBe([]);
    });
});

describe('checkPermission', function (): void {
    it('calls the bridge with correct function name', function (): void {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['status' => 'granted']);
        });

        $result = $this->notifications->checkPermission();

        expect($capturedFunction)->toBe('LocalNotifications.CheckPermission')
            ->and($result)->toBe(['status' => 'granted']);
    });

    it('handles denied status', function (): void {
        stubNativephpCall(fn () => json_encode(['status' => 'denied']));

        $result = $this->notifications->checkPermission();

        expect($result)->toBe(['status' => 'denied']);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->checkPermission();

        expect($result)->toBe([]);
    });
});

describe('update', function (): void {
    it('calls the bridge with correct function name and merged id', function (): void {
        $capturedFunction = null;
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedFunction, &$capturedData) {
            $capturedFunction = $function;
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true, 'id' => 'update-1']);
        });

        $result = $this->notifications->update('update-1', [
            'id' => 'ignored',
            'title' => 'Updated Title',
            'body' => 'Updated Body',
        ]);

        unset($capturedData['_config']);
        expect($capturedFunction)->toBe('LocalNotifications.Update')
            ->and($capturedData['id'])->toBe('update-1')
            ->and($capturedData['title'])->toBe('Updated Title')
            ->and($capturedData['body'])->toBe('Updated Body')
            ->and($result)->toBe(['success' => true, 'id' => 'update-1']);
    });

    it('overrides array id with the explicit id parameter', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->update('correct-id', [
            'id' => 'wrong-id',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData['id'])->toBe('correct-id');
    });

    it('accepts NotificationOptions DTO', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $options = new NotificationOptions(
            id: 'dto-id',
            title: 'DTO Title',
            body: 'DTO Body',
        );

        $this->notifications->update('override-id', $options);

        expect($capturedData['id'])->toBe('override-id')
            ->and($capturedData['title'])->toBe('DTO Title');
    });

    it('passes partial update options to the bridge', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->update('partial-update', [
            'id' => 'partial-update',
            'title' => 'New Title Only',
            'body' => 'Body',
        ]);

        unset($capturedData['_config']);
        expect($capturedData)->toBe([
            'id' => 'partial-update',
            'title' => 'New Title Only',
            'body' => 'Body',
        ]);
    });

    it('returns empty array when bridge returns null', function (): void {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->update('test-id', [
            'id' => 'test-id',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('returns empty array when bridge returns empty string', function (): void {
        stubNativephpCall(fn (): string => '');

        $result = $this->notifications->update('no-bridge', [
            'id' => 'no-bridge',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('injects _config into update bridge call', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->update('config-test', [
            'id' => 'config-test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($capturedData)->toHaveKey('_config')
            ->and($capturedData['_config'])->toHaveKeys([
                'channel_id',
                'max_actions',
                'default_sound',
            ]);
    });

    it('validates options through normalizeOptions', function (): void {
        $this->notifications->update('bad-update', [
            'id' => 'bad-update',
            'title' => 'Test',
            'body' => 'Body',
            'repeat' => 'daily',
            'repeatIntervalSeconds' => 3600,
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use both "repeat" and "repeatIntervalSeconds"');
});
