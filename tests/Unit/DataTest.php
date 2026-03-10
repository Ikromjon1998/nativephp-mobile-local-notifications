<?php

use Ikromjon\LocalNotifications\Data\NotificationAction;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\LocalNotifications;

describe('NotificationAction', function (): void {
    it('creates with required fields only', function (): void {
        $action = new NotificationAction(id: 'reply', title: 'Reply');

        expect($action->id)->toBe('reply')
            ->and($action->title)->toBe('Reply')
            ->and($action->destructive)->toBeFalse()
            ->and($action->input)->toBeFalse();
    });

    it('converts to array with only set flags', function (): void {
        $action = new NotificationAction(id: 'reply', title: 'Reply');

        expect($action->toArray())->toBe([
            'id' => 'reply',
            'title' => 'Reply',
        ]);
    });

    it('includes destructive flag when true', function (): void {
        $action = new NotificationAction(id: 'delete', title: 'Delete', destructive: true);

        expect($action->toArray())->toBe([
            'id' => 'delete',
            'title' => 'Delete',
            'destructive' => true,
        ]);
    });

    it('includes input flag when true', function (): void {
        $action = new NotificationAction(id: 'reply', title: 'Reply', input: true);

        expect($action->toArray())->toBe([
            'id' => 'reply',
            'title' => 'Reply',
            'input' => true,
        ]);
    });
});

describe('NotificationOptions', function (): void {
    it('creates with required fields only', function (): void {
        $options = new NotificationOptions(
            id: 'test-1',
            title: 'Test',
            body: 'Body',
        );

        expect($options->toArray())->toBe([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);
    });

    it('includes all optional fields when set', function (): void {
        $options = new NotificationOptions(
            id: 'full-test',
            title: 'Full',
            body: 'Body',
            delay: 60,
            at: 1700000000,
            repeat: RepeatInterval::Daily,
            sound: true,
            badge: 5,
            data: ['key' => 'value'],
            subtitle: 'Sub',
            image: 'https://example.com/img.jpg',
            bigText: 'Long text',
        );

        expect($options->toArray())->toBe([
            'id' => 'full-test',
            'title' => 'Full',
            'body' => 'Body',
            'delay' => 60,
            'at' => 1700000000,
            'repeat' => 'daily',
            'sound' => true,
            'badge' => 5,
            'data' => ['key' => 'value'],
            'subtitle' => 'Sub',
            'image' => 'https://example.com/img.jpg',
            'bigText' => 'Long text',
        ]);
    });

    it('converts RepeatInterval enum to string', function (): void {
        $options = new NotificationOptions(
            id: 'enum',
            title: 'Test',
            body: 'Body',
            repeat: RepeatInterval::Monthly,
        );

        expect($options->toArray()['repeat'])->toBe('monthly');
    });

    it('passes string repeat value unchanged', function (): void {
        $options = new NotificationOptions(
            id: 'string',
            title: 'Test',
            body: 'Body',
            repeat: 'weekly',
        );

        expect($options->toArray()['repeat'])->toBe('weekly');
    });

    it('converts actions to arrays', function (): void {
        $options = new NotificationOptions(
            id: 'actions-test',
            title: 'Test',
            body: 'Body',
            actions: [
                new NotificationAction(id: 'reply', title: 'Reply', input: true),
                new NotificationAction(id: 'delete', title: 'Delete', destructive: true),
            ],
        );

        $array = $options->toArray();

        expect($array['actions'])->toHaveCount(2)
            ->and($array['actions'][0])->toBe(['id' => 'reply', 'title' => 'Reply', 'input' => true])
            ->and($array['actions'][1])->toBe(['id' => 'delete', 'title' => 'Delete', 'destructive' => true]);
    });

    it('includes repeatIntervalSeconds in array', function (): void {
        $options = new NotificationOptions(
            id: 'custom',
            title: 'Custom',
            body: 'Body',
            repeatIntervalSeconds: 7200,
        );

        $array = $options->toArray();

        expect($array['repeatIntervalSeconds'])->toBe(7200)
            ->and($array)->not->toHaveKey('repeat');
    });

    it('throws when both repeat and repeatIntervalSeconds are set', function (): void {
        $options = new NotificationOptions(
            id: 'conflict',
            title: 'Conflict',
            body: 'Body',
            repeat: RepeatInterval::Daily,
            repeatIntervalSeconds: 3600,
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, 'Cannot use both "repeat" and "repeatIntervalSeconds"');

    it('throws when repeatIntervalSeconds is less than 60', function (): void {
        $options = new NotificationOptions(
            id: 'too-short',
            title: 'Too Short',
            body: 'Body',
            repeatIntervalSeconds: 30,
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, 'repeatIntervalSeconds must be at least 60 seconds');

    it('allows exactly 60 seconds for repeatIntervalSeconds', function (): void {
        $options = new NotificationOptions(
            id: 'min-interval',
            title: 'Min Interval',
            body: 'Body',
            repeatIntervalSeconds: 60,
        );

        expect($options->toArray()['repeatIntervalSeconds'])->toBe(60);
    });

    it('includes repeatDays in array', function (): void {
        $options = new NotificationOptions(
            id: 'weekdays',
            title: 'Weekday Alarm',
            body: 'Wake up!',
            at: 1700000000,
            repeatDays: [1, 2, 3, 4, 5],
        );

        $array = $options->toArray();

        expect($array['repeatDays'])->toBe([1, 2, 3, 4, 5])
            ->and($array)->not->toHaveKey('repeat')
            ->and($array)->not->toHaveKey('repeatIntervalSeconds');
    });

    it('deduplicates repeatDays', function (): void {
        $options = new NotificationOptions(
            id: 'dupes',
            title: 'Test',
            body: 'Body',
            at: 1700000000,
            repeatDays: [1, 1, 3, 3, 5],
        );

        expect($options->toArray()['repeatDays'])->toBe([1, 3, 5]);
    });

    it('throws when repeatDays used with repeat', function (): void {
        $options = new NotificationOptions(
            id: 'conflict',
            title: 'Test',
            body: 'Body',
            at: 1700000000,
            repeat: RepeatInterval::Daily,
            repeatDays: [1, 3],
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, 'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds"');

    it('throws when repeatDays used without at', function (): void {
        $options = new NotificationOptions(
            id: 'no-at',
            title: 'Test',
            body: 'Body',
            repeatDays: [1, 3],
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, '"repeatDays" requires "at" to determine the time of day');

    it('throws when repeatDays has invalid day number', function (): void {
        $options = new NotificationOptions(
            id: 'bad-day',
            title: 'Test',
            body: 'Body',
            at: 1700000000,
            repeatDays: [0, 8],
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, 'Each value in "repeatDays" must be between 1 (Monday) and 7 (Sunday)');

    it('includes repeatCount in array', function (): void {
        $options = new NotificationOptions(
            id: 'counted',
            title: 'Counted',
            body: 'Body',
            repeat: RepeatInterval::Daily,
            repeatCount: 5,
        );

        $array = $options->toArray();

        expect($array['repeatCount'])->toBe(5)
            ->and($array['repeat'])->toBe('daily');
    });

    it('includes repeatCount with repeatIntervalSeconds', function (): void {
        $options = new NotificationOptions(
            id: 'counted-custom',
            title: 'Counted Custom',
            body: 'Body',
            repeatIntervalSeconds: 3600,
            repeatCount: 10,
        );

        $array = $options->toArray();

        expect($array['repeatCount'])->toBe(10)
            ->and($array['repeatIntervalSeconds'])->toBe(3600);
    });

    it('includes repeatCount with repeatDays', function (): void {
        $options = new NotificationOptions(
            id: 'counted-days',
            title: 'Counted Days',
            body: 'Body',
            at: 1700000000,
            repeatDays: [1, 3, 5],
            repeatCount: 4,
        );

        $array = $options->toArray();

        expect($array['repeatCount'])->toBe(4)
            ->and($array['repeatDays'])->toBe([1, 3, 5]);
    });

    it('throws when repeatCount is less than 1', function (): void {
        $options = new NotificationOptions(
            id: 'bad-count',
            title: 'Bad Count',
            body: 'Body',
            repeat: RepeatInterval::Daily,
            repeatCount: 0,
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, 'repeatCount must be at least 1');

    it('throws when repeatCount is used without repeat mechanism', function (): void {
        $options = new NotificationOptions(
            id: 'no-repeat',
            title: 'No Repeat',
            body: 'Body',
            repeatCount: 3,
        );

        $options->toArray();
    })->throws(InvalidArgumentException::class, '"repeatCount" requires a repeat mechanism');

    it('can be passed to schedule method', function (): void {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData): string {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $notifications = new LocalNotifications;

        $options = new NotificationOptions(
            id: 'dto-test',
            title: 'DTO Test',
            body: 'Testing DTO',
            delay: 30,
            repeat: RepeatInterval::Hourly,
        );

        $result = $notifications->schedule($options);

        expect($result)->toBe(['success' => true])
            ->and($capturedData)->toBe([
                'id' => 'dto-test',
                'title' => 'DTO Test',
                'body' => 'Testing DTO',
                'delay' => 30,
                'repeat' => 'hourly',
            ]);
    });
});
