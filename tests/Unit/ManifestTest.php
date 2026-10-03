<?php

declare(strict_types=1);

/*
 * nativephp.json is read by the native build, not by any PHP code. A key
 * dropped from it still passes every other test and only fails on a device.
 */

it('registers the iOS notification delegate when the app launches', function (): void {
    $root = dirname(__DIR__, 2);

    /** @var array{ios: array<string, mixed>} $manifest */
    $manifest = json_decode((string) file_get_contents($root.'/nativephp.json'), true, flags: JSON_THROW_ON_ERROR);

    // iOS only hands over the tap that launched the app to a delegate that is
    // already set when launching finishes. Without this, cold-start taps are lost.
    expect($manifest['ios']['init_function'] ?? null)
        ->toBe('LocalNotificationDelegate.ensureRegistered');

    $swift = (string) file_get_contents($root.'/resources/ios/Sources/LocalNotificationsFunctions.swift');

    expect($swift)
        ->toContain('class LocalNotificationDelegate')
        ->toContain('static func ensureRegistered()');
});
