<?php

namespace Ikromjon\LocalNotifications\Events;

use Illuminate\Foundation\Events\Dispatchable;

class NotificationReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $body,
        public readonly ?array $data = null,
    ) {}
}
