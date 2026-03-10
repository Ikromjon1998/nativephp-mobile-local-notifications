<?php

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
