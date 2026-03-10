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
