<?php

declare(strict_types=1);

use Native\Mobile\Events\Contracts\DispatchableFromNative;

/*
 * Every event in this namespace is dispatched from Swift or Kotlin through
 * POST /_native/api/events. Since nativephp/mobile 3.3.11 and 4.6.1 that
 * endpoint only constructs classes that implement DispatchableFromNative and
 * answers {"success": false} for any other, so an event without the marker
 * never reaches Event::listen listeners or subscribers. An event that is only
 * ever dispatched from PHP must not carry the marker: exclude it here with
 * ->ignoring() when one is added.
 */
arch('events dispatched from native code implement DispatchableFromNative')
    ->expect('Ikromjon\LocalNotifications\Events')
    ->toImplement(DispatchableFromNative::class);
