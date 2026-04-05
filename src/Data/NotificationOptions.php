<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Data;

use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\Validation\NotificationValidator;

final readonly class NotificationOptions
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<int, int>|null  $repeatDays  Days of week (1=Monday through 7=Sunday)
     * @param  array<int, NotificationAction>|null  $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public ?int $delay = null,
        public ?int $at = null,
        public RepeatInterval|string|null $repeat = null,
        public ?int $repeatIntervalSeconds = null,
        public ?array $repeatDays = null,
        public ?int $repeatCount = null,
        public ?bool $sound = null,
        public ?int $badge = null,
        public ?array $data = null,
        public ?string $subtitle = null,
        public ?string $image = null,
        public ?string $bigText = null,
        public ?array $actions = null,
        public ?string $soundName = null,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function toArray(): array
    {
        $actionsArray = $this->actions !== null
            ? array_map(fn (NotificationAction $action): array => $action->toArray(), $this->actions)
            : null;

        NotificationValidator::validate([
            'repeat' => $this->repeat,
            'repeatIntervalSeconds' => $this->repeatIntervalSeconds,
            'repeatDays' => $this->repeatDays,
            'repeatCount' => $this->repeatCount,
            'at' => $this->at,
            'actions' => $actionsArray,
            'soundName' => $this->soundName,
        ]);

        $result = [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
        ];

        if ($this->delay !== null) {
            $result['delay'] = $this->delay;
        }

        if ($this->at !== null) {
            $result['at'] = $this->at;
        }

        if ($this->repeat !== null) {
            $result['repeat'] = $this->repeat instanceof RepeatInterval
                ? $this->repeat->value
                : $this->repeat;
        }

        if ($this->repeatIntervalSeconds !== null) {
            $result['repeatIntervalSeconds'] = $this->repeatIntervalSeconds;
        }

        if ($this->repeatDays !== null) {
            $result['repeatDays'] = array_values(array_unique($this->repeatDays));
        }

        if ($this->repeatCount !== null) {
            $result['repeatCount'] = $this->repeatCount;
        }

        if ($this->sound !== null) {
            $result['sound'] = $this->sound;
        }

        if ($this->soundName !== null) {
            $result['soundName'] = $this->soundName;
        }

        if ($this->badge !== null) {
            $result['badge'] = $this->badge;
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if ($this->subtitle !== null) {
            $result['subtitle'] = $this->subtitle;
        }

        if ($this->image !== null) {
            $result['image'] = $this->image;
        }

        if ($this->bigText !== null) {
            $result['bigText'] = $this->bigText;
        }

        if ($actionsArray !== null) {
            $result['actions'] = $actionsArray;
        }

        return $result;
    }
}
