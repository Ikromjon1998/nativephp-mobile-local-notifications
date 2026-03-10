<?php

namespace Ikromjon\LocalNotifications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationActionPressed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly string $actionId,
        public readonly ?array $data = null,
        public readonly ?string $inputText = null,
    ) {}
}
