<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

class NotificationActionPressed implements DispatchableFromNative
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
