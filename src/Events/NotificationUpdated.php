<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $body,
    ) {}
}
