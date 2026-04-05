<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Notifications;

use Ikromjon\LocalNotifications\Data\NotificationAction;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

class LocalNotificationMessage
{
    protected string $id;

    protected string $title = '';

    protected string $body = '';

    protected ?int $delay = null;

    protected ?int $at = null;

    protected RepeatInterval|string|null $repeat = null;

    protected ?int $repeatIntervalSeconds = null;

    /** @var array<int, int>|null */
    protected ?array $repeatDays = null;

    protected ?int $repeatCount = null;

    protected ?bool $sound = null;

    protected ?string $soundName = null;

    protected ?int $badge = null;

    /** @var array<string, mixed>|null */
    protected ?array $data = null;

    protected ?string $subtitle = null;

    protected ?string $image = null;

    protected ?string $bigText = null;

    /** @var array<int, NotificationAction>|null */
    protected ?array $actions = null;

    public function __construct()
    {
        $this->id = (string) mt_rand(100000, 999999);
    }

    public static function create(): self
    {
        return new self;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = $seconds;

        return $this;
    }

    public function at(int $timestamp): self
    {
        $this->at = $timestamp;

        return $this;
    }

    public function repeat(RepeatInterval|string $interval): self
    {
        $this->repeat = $interval;

        return $this;
    }

    public function repeatIntervalSeconds(int $seconds): self
    {
        $this->repeatIntervalSeconds = $seconds;

        return $this;
    }

    /**
     * @param  array<int, int>  $days  Days of week (1=Monday through 7=Sunday)
     */
    public function repeatDays(array $days): self
    {
        $this->repeatDays = $days;

        return $this;
    }

    public function repeatCount(int $count): self
    {
        $this->repeatCount = $count;

        return $this;
    }

    public function sound(bool|string $sound = true): self
    {
        if (is_string($sound)) {
            $this->soundName = $sound;
            $this->sound = true;
        } else {
            $this->sound = $sound;
        }

        return $this;
    }

    public function soundName(string $name): self
    {
        $this->soundName = $name;
        $this->sound = true;

        return $this;
    }

    public function badge(int $count): self
    {
        $this->badge = $count;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function image(string $url): self
    {
        $this->image = $url;

        return $this;
    }

    public function bigText(string $text): self
    {
        $this->bigText = $text;

        return $this;
    }

    public function action(string $id, string $title, bool $destructive = false, bool $input = false, ?int $snooze = null): self
    {
        $this->actions ??= [];
        $this->actions[] = new NotificationAction($id, $title, $destructive, $input, $snooze);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
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
            $result['repeatDays'] = $this->repeatDays;
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

        if ($this->actions !== null) {
            $result['actions'] = array_map(
                fn (NotificationAction $action): array => $action->toArray(),
                $this->actions,
            );
        }

        return $result;
    }
}
