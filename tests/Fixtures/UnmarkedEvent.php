<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Tests\Fixtures;

/**
 * Shaped like a plugin event but without DispatchableFromNative, as every
 * plugin event was before the marker existed.
 */
final class UnmarkedEvent
{
    public function __construct(
        public readonly string $id,
    ) {}
}
