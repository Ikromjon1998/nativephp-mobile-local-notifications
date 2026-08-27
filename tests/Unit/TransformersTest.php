<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\LocalNotifications;

beforeEach(function (): void {
    $this->notifications = new LocalNotifications;
});

/**
 * Capture the payload sent to the native bridge, with `_config` stripped.
 *
 * @param  array<string, mixed>|null  $captured
 */
function captureBridgePayload(&$captured): void
{
    stubNativephpCall(function (string $function, string $data) use (&$captured): string|false {
        $captured = json_decode($data, true);
        unset($captured['_config']);

        return json_encode(['success' => true]);
    });
}

describe('transformUsing', function (): void {
    it('rewrites payload content before it reaches the bridge', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $this->notifications->transformUsing(function (array $payload): array {
            $payload['title'] = strtoupper((string) $payload['title']);
            $payload['body'] = strtoupper((string) $payload['body']);

            return $payload;
        });

        $this->notifications->schedule([
            'id' => 'greeting',
            'title' => 'Hello',
            'body' => 'World',
        ]);

        expect($captured['title'])->toBe('HELLO')
            ->and($captured['body'])->toBe('WORLD');
    });

    it('leaves the payload untouched when no transformer is registered', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $this->notifications->schedule([
            'id' => 'greeting',
            'title' => 'Hello',
            'body' => 'World',
        ]);

        expect($captured)->toBe([
            'id' => 'greeting',
            'title' => 'Hello',
            'body' => 'World',
        ]);
    });

    it('applies multiple transformers in registration order (pipeline)', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $this->notifications
            ->transformUsing(function (array $payload): array {
                $payload['title'] .= '-a';

                return $payload;
            })
            ->transformUsing(function (array $payload): array {
                $payload['title'] .= '-b';

                return $payload;
            });

        $this->notifications->schedule([
            'id' => 'ordered',
            'title' => 'x',
            'body' => 'body',
        ]);

        expect($captured['title'])->toBe('x-a-b');
    });

    it('never exposes the internal _config block to transformers', function (): void {
        $seen = null;

        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $this->notifications->transformUsing(function (array $payload) use (&$seen): array {
            $seen = $payload;

            return $payload;
        });

        $this->notifications->schedule([
            'id' => 'no-config',
            'title' => 'Title',
            'body' => 'Body',
        ]);

        expect($seen)->not->toHaveKey('_config');
    });

    it('can localize nested action button titles', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $dictionary = ['action.done' => 'Fertig'];

        $this->notifications->transformUsing(function (array $payload) use ($dictionary): array {
            if (isset($payload['actions'])) {
                $payload['actions'] = array_map(function (array $action) use ($dictionary): array {
                    $action['title'] = $dictionary[$action['title']] ?? $action['title'];

                    return $action;
                }, $payload['actions']);
            }

            return $payload;
        });

        $this->notifications->schedule([
            'id' => 'with-actions',
            'title' => 'Title',
            'body' => 'Body',
            'actions' => [
                ['id' => 'done', 'title' => 'action.done'],
            ],
        ]);

        expect($captured['actions'][0]['title'])->toBe('Fertig');
    });

    it('also transforms update() payloads', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $this->notifications->transformUsing(function (array $payload): array {
            if (isset($payload['title'])) {
                $payload['title'] = '['.$payload['title'].']';
            }

            return $payload;
        });

        $this->notifications->update('reminder-1', [
            'title' => 'Updated',
            'body' => 'New body',
        ]);

        expect($captured['title'])->toBe('[Updated]')
            ->and($captured['id'])->toBe('reminder-1');
    });

    it('does not run transformers for non-content calls', function (): void {
        $runs = 0;

        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $this->notifications->transformUsing(function (array $payload) use (&$runs): array {
            $runs++;

            return $payload;
        });

        $this->notifications->cancel('some-id');
        $this->notifications->cancelAll();
        $this->notifications->checkPermission();
        $this->notifications->getPending();

        expect($runs)->toBe(0);
    });

    it('re-validates the transformed payload', function (): void {
        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $this->notifications->transformUsing(function (array $payload): array {
            $payload['id'] .= '_snooze';

            return $payload;
        });

        $this->notifications->schedule([
            'id' => 'reminder',
            'title' => 'Title',
            'body' => 'Body',
        ]);
    })->throws(InvalidArgumentException::class, 'reserved for internal sub-notifications');

    it('re-validates transformed update() payloads', function (): void {
        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $this->notifications->transformUsing(function (array $payload): array {
            $payload['id'] .= '_day_3';

            return $payload;
        });

        $this->notifications->update('habit', ['title' => 'Updated']);
    })->throws(InvalidArgumentException::class, 'reserved for internal sub-notifications');

    it('rejects a transformer that pushes the payload past the action limit', function (): void {
        stubNativephpCall(fn (): string => json_encode(['success' => true]));

        $this->notifications->transformUsing(function (array $payload): array {
            $payload['actions'] = [
                ['id' => 'a', 'title' => 'A'],
                ['id' => 'b', 'title' => 'B'],
                ['id' => 'c', 'title' => 'C'],
                ['id' => 'd', 'title' => 'D'],
            ];

            return $payload;
        });

        $this->notifications->schedule([
            'id' => 'too-many-actions',
            'title' => 'Title',
            'body' => 'Body',
        ]);
    })->throws(InvalidArgumentException::class, 'at most 3 action buttons');

    it('returns the instance for fluent chaining', function (): void {
        $result = $this->notifications->transformUsing(fn (array $payload): array => $payload);

        expect($result)->toBe($this->notifications);
    });

    it('is reachable through the container-bound contract', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $notifications = app(LocalNotificationsInterface::class);

        $notifications->transformUsing(function (array $payload): array {
            $payload['title'] = 'via-contract';

            return $payload;
        });

        $notifications->schedule([
            'id' => 'contract',
            'title' => 'original',
            'body' => 'Body',
        ]);

        expect($captured['title'])->toBe('via-contract');
    });
});

describe('flushTransformers', function (): void {
    it('removes all registered transformers', function (): void {
        $captured = null;
        captureBridgePayload($captured);

        $this->notifications->transformUsing(function (array $payload): array {
            $payload['title'] = 'transformed';

            return $payload;
        });

        $this->notifications->flushTransformers();

        $this->notifications->schedule([
            'id' => 'flushed',
            'title' => 'original',
            'body' => 'Body',
        ]);

        expect($captured['title'])->toBe('original');
    });

    it('returns the instance for fluent chaining', function (): void {
        expect($this->notifications->flushTransformers())->toBe($this->notifications);
    });
});
