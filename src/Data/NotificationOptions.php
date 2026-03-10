<?php

namespace Ikromjon\LocalNotifications\Data;

use Ikromjon\LocalNotifications\Enums\RepeatInterval;

final readonly class NotificationOptions
{
    /**
     * @param  array<string, mixed>|null  $data
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
        public ?bool $sound = null,
        public ?int $badge = null,
        public ?array $data = null,
        public ?string $subtitle = null,
        public ?string $image = null,
        public ?string $bigText = null,
        public ?array $actions = null,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function toArray(): array
    {
        if ($this->repeat !== null && $this->repeatIntervalSeconds !== null) {
            throw new \InvalidArgumentException(
                'Cannot use both "repeat" and "repeatIntervalSeconds". Choose one.',
            );
        }

        if ($this->repeatIntervalSeconds !== null && $this->repeatIntervalSeconds < 60) {
            throw new \InvalidArgumentException(
                'repeatIntervalSeconds must be at least 60 seconds.',
            );
        }

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

        if ($this->sound !== null) {
            $result['sound'] = $this->sound;
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

        if ($this->actions !== null) {
            $result['actions'] = array_map(
                fn (NotificationAction $action): array => $action->toArray(),
                $this->actions,
            );
        }

        return $result;
    }
}
