<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Tests\Fixtures\NativeEventPayloads;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

/*
 * NativePHP builds a native event with `new $event(...$payload)`: each payload
 * key becomes a named constructor argument. On nativephp/mobile 3.3.x a key the
 * constructor does not declare throws "Unknown named parameter" (4.6.1+ drops
 * the key and its value), and a missing required parameter throws an
 * ArgumentCountError on every version. These tests hold the payloads the
 * native code sends (tests/Fixtures/NativeEventPayloads.php) to the event
 * constructors, and that list to the native sources and nativephp.json.
 */

/**
 * @param  class-string  $event
 * @return list<ReflectionParameter>
 */
function nativeEventParameters(string $event): array
{
    return (new ReflectionClass($event))->getConstructor()?->getParameters() ?? [];
}

function pluginRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array{events: list<class-string>}
 */
function pluginManifest(): array
{
    /** @var array{events: list<class-string>} */
    return json_decode((string) file_get_contents(pluginRoot().'/nativephp.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<string>
 */
function nativeSources(): array
{
    return [
        ...glob(pluginRoot().'/resources/ios/Sources/*.swift') ?: [],
        ...glob(pluginRoot().'/resources/android/src/*.kt') ?: [],
    ];
}

it('declares every payload key as a constructor parameter', function (string $event, array $payload): void {
    $declared = array_map(fn (ReflectionParameter $parameter): string => $parameter->getName(), nativeEventParameters($event));

    expect(array_values(array_diff(array_keys($payload), $declared)))->toBe([]);
})->with(NativeEventPayloads::all());

it('sends every required constructor parameter', function (string $event, array $payload): void {
    $required = array_map(
        fn (ReflectionParameter $parameter): string => $parameter->getName(),
        array_filter(nativeEventParameters($event), fn (ReflectionParameter $parameter): bool => ! $parameter->isOptional()),
    );

    expect(array_values(array_diff($required, array_keys($payload))))->toBe([]);
})->with(NativeEventPayloads::all());

it('builds the event from the payload as named arguments, as NativePHP does', function (string $event, array $payload): void {
    $instance = new $event(...$payload);

    expect($instance)->toBeInstanceOf($event);

    foreach ($payload as $key => $value) {
        expect($instance->{$key})->toBe($value);
    }
})->with(NativeEventPayloads::all());

it('covers exactly the events listed in nativephp.json', function (): void {
    $events = pluginManifest()['events'];

    expect(array_values(array_unique(array_column(NativeEventPayloads::all(), 0))))->toEqualCanonicalizing($events);

    foreach ($events as $event) {
        expect(is_subclass_of($event, DispatchableFromNative::class))->toBeTrue($event.' must implement DispatchableFromNative');
    }
});

it('lists every event class the native code dispatches in nativephp.json', function (): void {
    $referenced = [];

    foreach (nativeSources() as $file) {
        // Swift and Kotlin spell the class name with escaped backslashes.
        preg_match_all('/Ikromjon\\\\+LocalNotifications\\\\+Events\\\\+(\w+)/', (string) file_get_contents($file), $matches);

        foreach ($matches[1] as $class) {
            $referenced[] = 'Ikromjon\\LocalNotifications\\Events\\'.$class;
        }
    }

    expect($referenced)->not->toBeEmpty()
        ->and(array_values(array_diff(array_unique($referenced), pluginManifest()['events'])))->toBe([]);
});

it('covers every key the native code adds to a payload after building it', function (): void {
    // Optional keys are added to an already built payload: `payload["key"] = …`
    // in Swift and `payload.put("key", …)` in Kotlin. Those are the ones a new
    // native feature tends to add without touching the PHP event.
    $added = [];

    foreach (nativeSources() as $file) {
        preg_match_all('/\bpayload(?:\["(\w+)"\]\s*=[^=]|\.put\("(\w+)")/', (string) file_get_contents($file), $matches);

        $added = [...$added, ...array_filter([...$matches[1], ...$matches[2]])];
    }

    $sent = array_merge(...array_map(fn (array $entry): array => array_keys($entry[1]), array_values(NativeEventPayloads::all())));

    expect($added)->not->toBeEmpty()
        ->and(array_values(array_diff(array_unique($added), $sent)))->toBe([]);
});
