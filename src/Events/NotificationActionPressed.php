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
     * @param  bool  $snoozed  True when the pressed action was a native snooze button.
     * @param  int|null  $snoozeSeconds  The snooze delay, sent together with $snoozed.
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly string $actionId,
        public readonly ?array $data = null,
        public readonly ?string $inputText = null,
        public readonly bool $snoozed = false,
        public readonly ?int $snoozeSeconds = null,
    ) {}
}
