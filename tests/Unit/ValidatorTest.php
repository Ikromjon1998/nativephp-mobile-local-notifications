<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Validation\NotificationValidator;

describe('empty and edge-case arrays', function (): void {
    it('allows empty repeatDays array without error', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows non-sequential repeatDays', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [7, 1],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows single-element repeatDays', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [4],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows repeatDays with boundary values 1 and 7', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [1, 7],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows actions as null', function (): void {
        expect(fn () => NotificationValidator::validate([
            'actions' => null,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('clamps min_repeat_interval_seconds to at least 1', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 0);

        expect(fn () => NotificationValidator::validate([
            'repeatIntervalSeconds' => 1,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('clamps max_actions to at least 1', function (): void {
        config()->set('local-notifications.max_actions', 0);

        expect(fn () => NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
            ],
        ]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('basic validation', function (): void {
    it('passes with valid minimal options', function (): void {
        expect(fn () => NotificationValidator::validate([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('passes with empty array', function (): void {
        expect(fn () => NotificationValidator::validate([]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('repeat mutual exclusivity', function (): void {
    it('throws when repeat and repeatIntervalSeconds are both set', function (): void {
        NotificationValidator::validate([
            'repeat' => 'daily',
            'repeatIntervalSeconds' => 3600,
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use both "repeat" and "repeatIntervalSeconds"');

    it('allows repeat without repeatIntervalSeconds', function (): void {
        expect(fn () => NotificationValidator::validate(['repeat' => 'daily']))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows repeatIntervalSeconds without repeat', function (): void {
        expect(fn () => NotificationValidator::validate(['repeatIntervalSeconds' => 120]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('repeatIntervalSeconds constraints', function (): void {
    it('throws when below default minimum of 60', function (): void {
        NotificationValidator::validate(['repeatIntervalSeconds' => 59]);
    })->throws(InvalidArgumentException::class, 'at least 60 seconds');

    it('allows exactly the minimum', function (): void {
        expect(fn () => NotificationValidator::validate(['repeatIntervalSeconds' => 60]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows values above the minimum', function (): void {
        expect(fn () => NotificationValidator::validate(['repeatIntervalSeconds' => 3600]))->not->toThrow(InvalidArgumentException::class);
    });

    it('respects custom min_repeat_interval_seconds from config', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        NotificationValidator::validate(['repeatIntervalSeconds' => 90]);
    })->throws(InvalidArgumentException::class, 'at least 120 seconds');

    it('allows value at custom minimum', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        expect(fn () => NotificationValidator::validate(['repeatIntervalSeconds' => 120]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('repeatDays validation', function (): void {
    it('throws when used with repeat', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [1, 3],
            'repeat' => 'daily',
            'at' => 1700000000,
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds"');

    it('throws when used with repeatIntervalSeconds', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [1, 3],
            'repeatIntervalSeconds' => 3600,
            'at' => 1700000000,
        ]);
    })->throws(InvalidArgumentException::class, 'Cannot use "repeatDays" with "repeat" or "repeatIntervalSeconds"');

    it('throws when at is missing', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [1, 3, 5],
        ]);
    })->throws(InvalidArgumentException::class, '"repeatDays" requires "at"');

    it('throws when day is below 1', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [0],
            'at' => 1700000000,
        ]);
    })->throws(InvalidArgumentException::class, 'between 1 (Monday) and 7 (Sunday)');

    it('throws when day is above 7', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [8],
            'at' => 1700000000,
        ]);
    })->throws(InvalidArgumentException::class, 'between 1 (Monday) and 7 (Sunday)');

    it('allows all valid days 1 through 7', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [1, 2, 3, 4, 5, 6, 7],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows single day', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [3],
            'at' => 1700000000,
        ]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('repeatCount validation', function (): void {
    it('throws when less than 1', function (): void {
        NotificationValidator::validate([
            'repeat' => 'daily',
            'repeatCount' => 0,
        ]);
    })->throws(InvalidArgumentException::class, 'repeatCount must be at least 1');

    it('throws with negative value', function (): void {
        NotificationValidator::validate([
            'repeat' => 'daily',
            'repeatCount' => -5,
        ]);
    })->throws(InvalidArgumentException::class, 'repeatCount must be at least 1');

    it('throws when no repeat mechanism is set', function (): void {
        NotificationValidator::validate([
            'repeatCount' => 3,
        ]);
    })->throws(InvalidArgumentException::class, '"repeatCount" requires a repeat mechanism');

    it('allows with repeat', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeat' => 'daily',
            'repeatCount' => 1,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows with repeatIntervalSeconds', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatIntervalSeconds' => 3600,
            'repeatCount' => 5,
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows with repeatDays', function (): void {
        expect(fn () => NotificationValidator::validate([
            'repeatDays' => [1, 3, 5],
            'at' => 1700000000,
            'repeatCount' => 10,
        ]))->not->toThrow(InvalidArgumentException::class);
    });
});

describe('soundName validation', function (): void {
    it('allows valid sound filenames', function (): void {
        expect(fn () => NotificationValidator::validate([
            'soundName' => 'alert.wav',
        ]))->not->toThrow(InvalidArgumentException::class);

        expect(fn () => NotificationValidator::validate([
            'soundName' => 'my-sound.caf',
        ]))->not->toThrow(InvalidArgumentException::class);

        expect(fn () => NotificationValidator::validate([
            'soundName' => 'notification_tone.mp3',
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('throws when soundName has no extension', function (): void {
        NotificationValidator::validate(['soundName' => 'alert']);
    })->throws(InvalidArgumentException::class, 'filename with extension');

    it('throws when soundName has invalid characters', function (): void {
        NotificationValidator::validate(['soundName' => 'my sound.wav']);
    })->throws(InvalidArgumentException::class, 'filename with extension');

    it('throws when soundName is empty string', function (): void {
        NotificationValidator::validate(['soundName' => '']);
    })->throws(InvalidArgumentException::class, 'filename with extension');

    it('throws when soundName has path separators', function (): void {
        NotificationValidator::validate(['soundName' => 'sounds/alert.wav']);
    })->throws(InvalidArgumentException::class, 'filename with extension');
});

describe('actions validation', function (): void {
    it('throws when actions exceed default max of 3', function (): void {
        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
                ['id' => 'a3', 'title' => 'A3'],
                ['id' => 'a4', 'title' => 'A4'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 3 action buttons');

    it('allows exactly max actions', function (): void {
        expect(fn () => NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
                ['id' => 'a3', 'title' => 'A3'],
            ],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows fewer than max actions', function (): void {
        expect(fn () => NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
            ],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows empty actions array', function (): void {
        expect(fn () => NotificationValidator::validate([
            'actions' => [],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('skips validation when actions is not an array', function (): void {
        expect(fn () => NotificationValidator::validate([
            'actions' => 'not-an-array',
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('respects custom max_actions from config', function (): void {
        config()->set('local-notifications.max_actions', 2);

        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
                ['id' => 'a3', 'title' => 'A3'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 2 action buttons');

    it('allows at custom max_actions limit', function (): void {
        config()->set('local-notifications.max_actions', 2);

        expect(fn () => NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
            ],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('allows max_actions set to 1', function (): void {
        config()->set('local-notifications.max_actions', 1);

        expect(fn () => NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
            ],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('throws at 2 when max_actions is 1', function (): void {
        config()->set('local-notifications.max_actions', 1);

        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 1 action button');
});
