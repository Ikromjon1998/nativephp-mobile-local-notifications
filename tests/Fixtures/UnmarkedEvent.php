<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Tests\Fixtures;

/**
 * Shaped like a plugin event but without DispatchableFromNative, as every
 * plugin event was before the marker existed.
 */
final readonly class UnmarkedEvent
{
    public function __construct(
        public string $id,
    ) {}
}
