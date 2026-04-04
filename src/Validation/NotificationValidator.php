<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Validation;

final class NotificationValidator
{
    /**
     * Validate scheduling options for mutual exclusivity and constraints.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws \InvalidArgumentException
     */
    public static function validate(array $options): void
    {
        $hasRepeat = isset($options['repeat']);
        $hasRepeatIntervalSeconds = isset($options['repeatIntervalSeconds']);
        $hasRepeatDays = isset($options['repeatDays']);
        $hasRepeatCount = isset($options['repeatCount']);

        if ($hasRepeat && $hasRepeatIntervalSeconds) {
            throw new \InvalidArgumentException(
                'Cannot use both "repeat" and "repeatIntervalSeconds". Choose one.',
            );
        }

        $minInterval = max(1, (int) self::configValue('min_repeat_interval_seconds', 60));

        if ($hasRepeatIntervalSeconds && $options['repeatIntervalSeconds'] < $minInterval) {
            throw new \InvalidArgumentException(
                "repeatIntervalSeconds must be at least {$minInterval} seconds.",
            );
        }

        if ($hasRepeatDays) {
            if ($hasRepeat || $hasRepeatIntervalSeconds) {
                throw new \InvalidArgumentException(
                    'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds".',
                );
            }

            if (! isset($options['at'])) {
                throw new \InvalidArgumentException(
                    '"repeatDays" requires "at" to determine the time of day.',
                );
            }

            foreach ($options['repeatDays'] as $day) {
                if ($day < 1 || $day > 7) {
                    throw new \InvalidArgumentException(
                        'Each value in "repeatDays" must be between 1 (Monday) and 7 (Sunday).',
                    );
                }
            }
        }

        if ($hasRepeatCount) {
            if ($options['repeatCount'] < 1) {
                throw new \InvalidArgumentException(
                    'repeatCount must be at least 1.',
                );
            }

            if (! $hasRepeat && ! $hasRepeatIntervalSeconds && ! $hasRepeatDays) {
                throw new \InvalidArgumentException(
                    '"repeatCount" requires a repeat mechanism ("repeat", "repeatIntervalSeconds", or "repeatDays").',
                );
            }
        }

        if (isset($options['actions'])) {
            $maxActions = max(1, (int) self::configValue('max_actions', 3));
            if (is_array($options['actions']) && count($options['actions']) > $maxActions) {
                $label = $maxActions === 1 ? 'action button' : 'action buttons';
                throw new \InvalidArgumentException(
                    "A notification may have at most {$maxActions} {$label}.",
                );
            }
        }
    }

    /**
     * Read a value from the local-notifications config, with a fallback
     * for environments where the Laravel config helper is not available.
     */
    private static function configValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config("local-notifications.{$key}", $default);
        }

        return $default;
    }
}
