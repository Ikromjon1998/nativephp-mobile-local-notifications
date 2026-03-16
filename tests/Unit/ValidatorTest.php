<?php

use Ikromjon\LocalNotifications\Validation\NotificationValidator;

describe('basic validation', function (): void {
    it('passes with valid minimal options', function (): void {
        NotificationValidator::validate([
            'id' => 'test',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        expect(true)->toBeTrue();
    });

    it('passes with empty array', function (): void {
        NotificationValidator::validate([]);

        expect(true)->toBeTrue();
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
        NotificationValidator::validate(['repeat' => 'daily']);

        expect(true)->toBeTrue();
    });

    it('allows repeatIntervalSeconds without repeat', function (): void {
        NotificationValidator::validate(['repeatIntervalSeconds' => 120]);

        expect(true)->toBeTrue();
    });
});

describe('repeatIntervalSeconds constraints', function (): void {
    it('throws when below default minimum of 60', function (): void {
        NotificationValidator::validate(['repeatIntervalSeconds' => 59]);
    })->throws(InvalidArgumentException::class, 'at least 60 seconds');

    it('allows exactly the minimum', function (): void {
        NotificationValidator::validate(['repeatIntervalSeconds' => 60]);

        expect(true)->toBeTrue();
    });

    it('allows values above the minimum', function (): void {
        NotificationValidator::validate(['repeatIntervalSeconds' => 3600]);

        expect(true)->toBeTrue();
    });

    it('respects custom min_repeat_interval_seconds from config', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        NotificationValidator::validate(['repeatIntervalSeconds' => 90]);
    })->throws(InvalidArgumentException::class, 'at least 120 seconds');

    it('allows value at custom minimum', function (): void {
        config()->set('local-notifications.min_repeat_interval_seconds', 120);

        NotificationValidator::validate(['repeatIntervalSeconds' => 120]);

        expect(true)->toBeTrue();
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
        NotificationValidator::validate([
            'repeatDays' => [1, 2, 3, 4, 5, 6, 7],
            'at' => 1700000000,
        ]);

        expect(true)->toBeTrue();
    });

    it('allows single day', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [3],
            'at' => 1700000000,
        ]);

        expect(true)->toBeTrue();
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
        NotificationValidator::validate([
            'repeat' => 'daily',
            'repeatCount' => 1,
        ]);

        expect(true)->toBeTrue();
    });

    it('allows with repeatIntervalSeconds', function (): void {
        NotificationValidator::validate([
            'repeatIntervalSeconds' => 3600,
            'repeatCount' => 5,
        ]);

        expect(true)->toBeTrue();
    });

    it('allows with repeatDays', function (): void {
        NotificationValidator::validate([
            'repeatDays' => [1, 3, 5],
            'at' => 1700000000,
            'repeatCount' => 10,
        ]);

        expect(true)->toBeTrue();
    });
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
        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
                ['id' => 'a3', 'title' => 'A3'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('allows fewer than max actions', function (): void {
        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('allows empty actions array', function (): void {
        NotificationValidator::validate([
            'actions' => [],
        ]);

        expect(true)->toBeTrue();
    });

    it('skips validation when actions is not an array', function (): void {
        NotificationValidator::validate([
            'actions' => 'not-an-array',
        ]);

        expect(true)->toBeTrue();
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

        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('allows max_actions set to 1', function (): void {
        config()->set('local-notifications.max_actions', 1);

        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
            ],
        ]);

        expect(true)->toBeTrue();
    });

    it('throws at 2 when max_actions is 1', function (): void {
        config()->set('local-notifications.max_actions', 1);

        NotificationValidator::validate([
            'actions' => [
                ['id' => 'a1', 'title' => 'A1'],
                ['id' => 'a2', 'title' => 'A2'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'at most 1 action buttons');
});
