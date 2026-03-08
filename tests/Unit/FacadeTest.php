<?php

use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Ikromjon\LocalNotifications\LocalNotifications as LocalNotificationsClass;

it('resolves to the correct class', function () {
    $facade = LocalNotifications::getFacadeRoot();

    expect($facade)->toBeInstanceOf(LocalNotificationsClass::class);
});

it('proxies schedule calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['success' => true, 'id' => 'facade-test']));

    $result = LocalNotifications::schedule([
        'id' => 'facade-test',
        'title' => 'Facade Test',
        'body' => 'Testing facade',
    ]);

    expect($result)->toBe(['success' => true, 'id' => 'facade-test']);
});

it('proxies cancel calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['success' => true]));

    $result = LocalNotifications::cancel('some-id');

    expect($result)->toBe(['success' => true]);
});

it('proxies cancelAll calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['success' => true]));

    $result = LocalNotifications::cancelAll();

    expect($result)->toBe(['success' => true]);
});

it('proxies getPending calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['success' => true, 'count' => 0]));

    $result = LocalNotifications::getPending();

    expect($result)->toBe(['success' => true, 'count' => 0]);
});

it('proxies requestPermission calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['granted' => true]));

    $result = LocalNotifications::requestPermission();

    expect($result)->toBe(['granted' => true]);
});

it('proxies checkPermission calls to the underlying class', function () {
    stubNativephpCall(fn () => json_encode(['status' => 'granted']));

    $result = LocalNotifications::checkPermission();

    expect($result)->toBe(['status' => 'granted']);
});
