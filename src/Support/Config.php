<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Support;

/**
 * Thin wrapper around Laravel's config() helper with a graceful
 * fallback for environments where the helper is not available.
 */
final class Config
{
    /**
     * Read a value from the local-notifications config.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config("local-notifications.{$key}", $default);
        }

        return $default;
    }
}
