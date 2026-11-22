<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

class PermissionDenied implements DispatchableFromNative
{
    use Dispatchable;
    use SerializesModels;
}
