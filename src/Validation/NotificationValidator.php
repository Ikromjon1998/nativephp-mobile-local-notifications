<?php

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

        if ($hasRepeatIntervalSeconds && $options['repeatIntervalSeconds'] < 60) {
            throw new \InvalidArgumentException(
                'repeatIntervalSeconds must be at least 60 seconds.',
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
    }
}
