<?php

use Ikromjon\LocalNotifications\LocalNotifications;

beforeEach(function () {
    $this->notifications = new LocalNotifications;
});

describe('schedule', function () {
    it('calls the bridge with correct function name and options', function () {
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

    it('passes all optional parameters to the bridge', function () {
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
        ]);
    });

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('returns empty array when bridge returns invalid json', function () {
        stubNativephpCall(fn () => 'not-json');

        $result = $this->notifications->schedule([
            'id' => 'test-1',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect($result)->toBe([]);
    });

    it('handles empty options array', function () {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->schedule([]);

        expect($capturedData)->toBe([]);
    });

    it('preserves nested data arrays', function () {
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
});

describe('cancel', function () {
    it('calls the bridge with correct function name and id', function () {
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

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->cancel('test-id');

        expect($result)->toBe([]);
    });

    it('handles special characters in id', function () {
        $capturedData = null;

        stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
            $capturedData = json_decode($data, true);

            return json_encode(['success' => true]);
        });

        $this->notifications->cancel('id-with-special/chars.and:colons');

        expect($capturedData['id'])->toBe('id-with-special/chars.and:colons');
    });
});

describe('cancelAll', function () {
    it('calls the bridge with correct function name and no data', function () {
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

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->cancelAll();

        expect($result)->toBe([]);
    });
});

describe('getPending', function () {
    it('calls the bridge with correct function name', function () {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['success' => true, 'notifications' => '[]', 'count' => 0]);
        });

        $result = $this->notifications->getPending();

        expect($capturedFunction)->toBe('LocalNotifications.GetPending')
            ->and($result)->toBe(['success' => true, 'notifications' => '[]', 'count' => 0]);
    });

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->getPending();

        expect($result)->toBe([]);
    });
});

describe('requestPermission', function () {
    it('calls the bridge with correct function name', function () {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['granted' => true]);
        });

        $result = $this->notifications->requestPermission();

        expect($capturedFunction)->toBe('LocalNotifications.RequestPermission')
            ->and($result)->toBe(['granted' => true]);
    });

    it('handles denied permission response', function () {
        stubNativephpCall(fn () => json_encode(['granted' => false, 'status' => 'pending']));

        $result = $this->notifications->requestPermission();

        expect($result)->toBe(['granted' => false, 'status' => 'pending']);
    });

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->requestPermission();

        expect($result)->toBe([]);
    });
});

describe('checkPermission', function () {
    it('calls the bridge with correct function name', function () {
        $capturedFunction = null;

        stubNativephpCall(function (string $function) use (&$capturedFunction) {
            $capturedFunction = $function;

            return json_encode(['status' => 'granted']);
        });

        $result = $this->notifications->checkPermission();

        expect($capturedFunction)->toBe('LocalNotifications.CheckPermission')
            ->and($result)->toBe(['status' => 'granted']);
    });

    it('handles denied status', function () {
        stubNativephpCall(fn () => json_encode(['status' => 'denied']));

        $result = $this->notifications->checkPermission();

        expect($result)->toBe(['status' => 'denied']);
    });

    it('returns empty array when bridge returns null', function () {
        stubNativephpCallReturnsNull();

        $result = $this->notifications->checkPermission();

        expect($result)->toBe([]);
    });
});
