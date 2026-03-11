<?php

namespace Ikromjon\LocalNotifications\Data;

final readonly class NotificationAction
{
    public function __construct(
        public string $id,
        public string $title,
        public bool $destructive = false,
        public bool $input = false,
    ) {}

    /**
     * @return array{id: string, title: string, destructive?: bool, input?: bool}
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
        ];

        if ($this->destructive) {
            $data['destructive'] = true;
        }

        if ($this->input) {
            $data['input'] = true;
        }

        return $data;
    }
}
