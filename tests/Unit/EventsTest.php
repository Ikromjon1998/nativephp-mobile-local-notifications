<?php

use Ikromjon\LocalNotifications\Events\NotificationActionPressed;
use Ikromjon\LocalNotifications\Events\NotificationReceived;
use Ikromjon\LocalNotifications\Events\NotificationScheduled;
use Ikromjon\LocalNotifications\Events\NotificationTapped;
use Ikromjon\LocalNotifications\Events\PermissionDenied;
use Ikromjon\LocalNotifications\Events\PermissionGranted;

describe('NotificationScheduled', function (): void {
    it('stores id, title, and body', function (): void {
        $event = new NotificationScheduled(
            id: 'test-id',
            title: 'Test Title',
            body: 'Test Body',
        );

        expect($event->id)->toBe('test-id')
            ->and($event->title)->toBe('Test Title')
            ->and($event->body)->toBe('Test Body');
    });

    it('has readonly properties', function (): void {
        $event = new NotificationScheduled('id', 'title', 'body');

        $reflection = new ReflectionClass($event);

        expect($reflection->getProperty('id')->isReadOnly())->toBeTrue()
            ->and($reflection->getProperty('title')->isReadOnly())->toBeTrue()
            ->and($reflection->getProperty('body')->isReadOnly())->toBeTrue();
    });
});

describe('NotificationReceived', function (): void {
    it('stores id, title, body, and data', function (): void {
        $event = new NotificationReceived(
            id: 'recv-id',
            title: 'Received Title',
            body: 'Received Body',
            data: ['key' => 'value'],
        );

        expect($event->id)->toBe('recv-id')
            ->and($event->title)->toBe('Received Title')
            ->and($event->body)->toBe('Received Body')
            ->and($event->data)->toBe(['key' => 'value']);
    });

    it('defaults data to null', function (): void {
        $event = new NotificationReceived('id', 'title', 'body');

        expect($event->data)->toBeNull();
    });
});

describe('NotificationTapped', function (): void {
    it('stores id, title, body, and data', function (): void {
        $event = new NotificationTapped(
            id: 'tap-id',
            title: 'Tapped Title',
            body: 'Tapped Body',
            data: ['task_id' => 42],
        );

        expect($event->id)->toBe('tap-id')
            ->and($event->title)->toBe('Tapped Title')
            ->and($event->body)->toBe('Tapped Body')
            ->and($event->data)->toBe(['task_id' => 42]);
    });

    it('defaults data to null', function (): void {
        $event = new NotificationTapped('id', 'title', 'body');

        expect($event->data)->toBeNull();
    });
});

describe('PermissionGranted', function (): void {
    it('can be instantiated with no arguments', function (): void {
        $event = new PermissionGranted;

        expect($event)->toBeInstanceOf(PermissionGranted::class);
    });
});

describe('PermissionDenied', function (): void {
    it('can be instantiated with no arguments', function (): void {
        $event = new PermissionDenied;

        expect($event)->toBeInstanceOf(PermissionDenied::class);
    });
});

describe('NotificationActionPressed', function (): void {
    it('stores notificationId, actionId, data, and inputText', function (): void {
        $event = new NotificationActionPressed(
            notificationId: 'notif-1',
            actionId: 'reply',
            data: ['key' => 'value'],
            inputText: 'Hello!',
        );

        expect($event->notificationId)->toBe('notif-1')
            ->and($event->actionId)->toBe('reply')
            ->and($event->data)->toBe(['key' => 'value'])
            ->and($event->inputText)->toBe('Hello!');
    });

    it('defaults data and inputText to null', function (): void {
        $event = new NotificationActionPressed('notif-1', 'snooze');

        expect($event->data)->toBeNull()
            ->and($event->inputText)->toBeNull();
    });
});
