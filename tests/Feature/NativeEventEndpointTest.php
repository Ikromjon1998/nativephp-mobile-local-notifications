<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Ikromjon\LocalNotifications\Tests\Fixtures\NativeEventPayloads;
use Ikromjon\LocalNotifications\Tests\Fixtures\UnmarkedEvent;
use Illuminate\Support\Facades\Event;
use Native\Mobile\NativeServiceProvider;
use Native\Mobile\Support\NativeEventSignature;

/*
 * The native shell delivers an event by POSTing {"event": <class>, "payload":
 * {...}} to /_native/api/events. These tests post every payload the native
 * code sends through the installed core's route and controller and check that
 * a plain Event::listen listener receives it with its values intact.
 */

afterEach(function (): void {
    putenv('NATIVEPHP_EVENTS_SIGNED');
});

/**
 * @return ArrayObject<int, object>
 */
function listenFor(string $event): ArrayObject
{
    $received = new ArrayObject;

    Event::listen($event, function (object $dispatched) use ($received): void {
        $received[] = $dispatched;
    });

    return $received;
}

it('runs against the installed core without booting NativeServiceProvider', function (): void {
    $jumpBridge = array_filter(get_included_files(), fn (string $file): bool => str_ends_with($file, 'jump_bridge_functions.php'));

    expect(InstalledVersions::getPrettyVersion('nativephp/mobile'))->not->toBeNull()
        ->and(app()->getProviders(NativeServiceProvider::class))->toBe([])
        ->and($jumpBridge)->toBe([]);
});

it('refuses an event class without DispatchableFromNative, as plugin events were before', function (): void {
    $received = listenFor(UnmarkedEvent::class);

    $this->postJson('/_native/api/events', ['event' => UnmarkedEvent::class, 'payload' => ['id' => 'water-reminder']])
        ->assertOk()
        ->assertJson(['success' => false]);

    expect($received)->toHaveCount(0);
});

it('delivers the event to Event::listen listeners', function (string $event, array $payload): void {
    $received = listenFor($event);

    $this->postJson('/_native/api/events', ['event' => $event, 'payload' => $payload])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($received)->toHaveCount(1)
        ->and($received[0])->toBeInstanceOf($event);

    foreach ($payload as $key => $value) {
        expect($received[0]->{$key})->toBe($value);
    }
})->with(NativeEventPayloads::all());

it('delivers the event when the native shell signs its events', function (string $event, array $payload): void {
    putenv('NATIVEPHP_EVENTS_SIGNED=1');
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');

    expect(NativeEventSignature::enforced())->toBeTrue();

    $received = listenFor($event);

    // The bytes the shell signs and posts. The format is the shared protocol
    // in Native\Mobile\Support\NativeEventSignature and the Swift/Kotlin twins.
    $body = json_encode(['event' => $event, 'payload' => $payload], JSON_THROW_ON_ERROR);
    $nonce = bin2hex(random_bytes(16));
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', "nativephp.native-event\n{$nonce}\n{$timestamp}\n{$body}", app('encrypter')->getKey());

    $this->call('POST', '/_native/api/events', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_NATIVE_EVENT_NONCE' => $nonce,
        'HTTP_X_NATIVE_EVENT_TIMESTAMP' => $timestamp,
        'HTTP_X_NATIVE_EVENT_SIGNATURE' => $signature,
    ], content: $body)
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($received)->toHaveCount(1);
})->with(NativeEventPayloads::all())
    ->skip(! class_exists(NativeEventSignature::class), 'nativephp/mobile signs native events from 3.3.12 and 4.8.0');
