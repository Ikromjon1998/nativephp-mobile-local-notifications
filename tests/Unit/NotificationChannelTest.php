<?php

declare(strict_types=1);

use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\Notifications\LocalNotificationChannel;
use Ikromjon\LocalNotifications\Notifications\LocalNotificationMessage;
use Illuminate\Notifications\Notification;

// --- LocalNotificationMessage tests ---

it('creates a message with static factory', function (): void {
    $message = LocalNotificationMessage::create();

    expect($message)->toBeInstanceOf(LocalNotificationMessage::class);
});

it('builds a basic message with title and body', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('test-1')
        ->title('Hello')
        ->body('World')
        ->toArray();

    expect($array)
        ->toHaveKey('id', 'test-1')
        ->toHaveKey('title', 'Hello')
        ->toHaveKey('body', 'World');
});

it('generates an id when none is set', function (): void {
    $array = LocalNotificationMessage::create()
        ->title('Hello')
        ->body('World')
        ->toArray();

    expect($array['id'])->toBeString()->not->toBeEmpty();
});

it('sets delay in seconds', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('delay-test')
        ->title('Test')
        ->body('Body')
        ->delay(60)
        ->toArray();

    expect($array)->toHaveKey('delay', 60);
});

it('sets at timestamp', function (): void {
    $ts = time() + 3600;

    $array = LocalNotificationMessage::create()
        ->id('at-test')
        ->title('Test')
        ->body('Body')
        ->at($ts)
        ->toArray();

    expect($array)->toHaveKey('at', $ts);
});

it('sets repeat interval with enum', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('repeat-test')
        ->title('Test')
        ->body('Body')
        ->repeat(RepeatInterval::Daily)
        ->toArray();

    expect($array)->toHaveKey('repeat', 'daily');
});

it('sets repeat interval with string', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('repeat-test')
        ->title('Test')
        ->body('Body')
        ->repeat('hourly')
        ->toArray();

    expect($array)->toHaveKey('repeat', 'hourly');
});

it('sets custom repeat interval seconds', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('custom-repeat')
        ->title('Test')
        ->body('Body')
        ->repeatIntervalSeconds(300)
        ->toArray();

    expect($array)->toHaveKey('repeatIntervalSeconds', 300);
});

it('sets repeat days', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('days-test')
        ->title('Test')
        ->body('Body')
        ->repeatDays([1, 3, 5])
        ->toArray();

    expect($array)->toHaveKey('repeatDays', [1, 3, 5]);
});

it('sets repeat count', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('count-test')
        ->title('Test')
        ->body('Body')
        ->repeatCount(5)
        ->toArray();

    expect($array)->toHaveKey('repeatCount', 5);
});

it('sets sound', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('sound-test')
        ->title('Test')
        ->body('Body')
        ->sound()
        ->toArray();

    expect($array)->toHaveKey('sound', true);
});

it('sets custom sound name via sound() string overload', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('sound-name-test')
        ->title('Test')
        ->body('Body')
        ->sound('alert.wav')
        ->toArray();

    expect($array)->toHaveKey('sound', true)
        ->toHaveKey('soundName', 'alert.wav');
});

it('sets custom sound name via soundName()', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('sound-name-test')
        ->title('Test')
        ->body('Body')
        ->soundName('chime.caf')
        ->toArray();

    expect($array)->toHaveKey('sound', true)
        ->toHaveKey('soundName', 'chime.caf');
});

it('disables sound', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('sound-test')
        ->title('Test')
        ->body('Body')
        ->sound(false)
        ->toArray();

    expect($array)->toHaveKey('sound', false);
});

it('sets badge count', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('badge-test')
        ->title('Test')
        ->body('Body')
        ->badge(3)
        ->toArray();

    expect($array)->toHaveKey('badge', 3);
});

it('sets custom data', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('data-test')
        ->title('Test')
        ->body('Body')
        ->data(['key' => 'value'])
        ->toArray();

    expect($array)->toHaveKey('data', ['key' => 'value']);
});

it('sets subtitle', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('sub-test')
        ->title('Test')
        ->body('Body')
        ->subtitle('Category')
        ->toArray();

    expect($array)->toHaveKey('subtitle', 'Category');
});

it('sets image url', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('img-test')
        ->title('Test')
        ->body('Body')
        ->image('https://example.com/photo.jpg')
        ->toArray();

    expect($array)->toHaveKey('image', 'https://example.com/photo.jpg');
});

it('sets big text', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('big-test')
        ->title('Test')
        ->body('Body')
        ->bigText('A much longer description text')
        ->toArray();

    expect($array)->toHaveKey('bigText', 'A much longer description text');
});

it('adds action buttons', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('action-test')
        ->title('Friend Request')
        ->body('John wants to connect')
        ->action('accept', 'Accept')
        ->action('decline', 'Decline', destructive: true)
        ->action('view', 'View Profile')
        ->toArray();

    expect($array['actions'])->toHaveCount(3)
        ->and($array['actions'][0])->toBe(['id' => 'accept', 'title' => 'Accept'])
        ->and($array['actions'][1])->toBe(['id' => 'decline', 'title' => 'Decline', 'destructive' => true])
        ->and($array['actions'][2])->toBe(['id' => 'view', 'title' => 'View Profile']);
});

it('adds input action', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('input-test')
        ->title('Message')
        ->body('New message from Alice')
        ->action('reply', 'Reply', input: true)
        ->toArray();

    expect($array['actions'][0])->toBe(['id' => 'reply', 'title' => 'Reply', 'input' => true]);
});

it('omits null fields from array', function (): void {
    $array = LocalNotificationMessage::create()
        ->id('minimal')
        ->title('Title')
        ->body('Body')
        ->toArray();

    expect($array)->toBe([
        'id' => 'minimal',
        'title' => 'Title',
        'body' => 'Body',
    ]);
});

it('supports fluent chaining for all options', function (): void {
    $ts = time() + 3600;

    $array = LocalNotificationMessage::create()
        ->id('full-test')
        ->title('Full Test')
        ->body('Everything')
        ->subtitle('Sub')
        ->delay(10)
        ->at($ts)
        ->repeat(RepeatInterval::Weekly)
        ->repeatCount(4)
        ->sound()
        ->badge(1)
        ->data(['user_id' => 42])
        ->image('https://example.com/img.png')
        ->bigText('Extended content')
        ->action('ok', 'OK')
        ->toArray();

    expect($array)
        ->toHaveKey('id', 'full-test')
        ->toHaveKey('title', 'Full Test')
        ->toHaveKey('body', 'Everything')
        ->toHaveKey('subtitle', 'Sub')
        ->toHaveKey('delay', 10)
        ->toHaveKey('at', $ts)
        ->toHaveKey('repeat', 'weekly')
        ->toHaveKey('repeatCount', 4)
        ->toHaveKey('sound', true)
        ->toHaveKey('badge', 1)
        ->toHaveKey('data', ['user_id' => 42])
        ->toHaveKey('image', 'https://example.com/img.png')
        ->toHaveKey('bigText', 'Extended content')
        ->toHaveKey('actions');
});

// --- LocalNotificationChannel tests ---

it('sends notification via the channel', function (): void {
    stubNativephpCall(fn () => json_encode(['success' => true, 'id' => 'channel-test']));

    $channel = $this->app->make(LocalNotificationChannel::class);

    $notification = new class extends Notification
    {
        public function toLocalNotification(object $notifiable): LocalNotificationMessage
        {
            return LocalNotificationMessage::create()
                ->id('channel-test')
                ->title('Channel Test')
                ->body('Sent via channel');
        }
    };

    $result = $channel->send(new class {}, $notification);

    expect($result)->toBe(['success' => true, 'id' => 'channel-test']);
});

it('sends notification with actions via the channel', function (): void {
    $capturedData = null;

    stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
        $capturedData = json_decode($data, true);

        return json_encode(['success' => true]);
    });

    $channel = $this->app->make(LocalNotificationChannel::class);

    $notification = new class extends Notification
    {
        public function toLocalNotification(object $notifiable): LocalNotificationMessage
        {
            return LocalNotificationMessage::create()
                ->id('action-channel')
                ->title('Friend Request')
                ->body('John wants to connect')
                ->subtitle('Social')
                ->action('accept', 'Accept')
                ->action('decline', 'Decline', destructive: true);
        }
    };

    $channel->send(new class {}, $notification);

    expect($capturedData)
        ->toHaveKey('id', 'action-channel')
        ->toHaveKey('title', 'Friend Request')
        ->toHaveKey('subtitle', 'Social')
        ->and($capturedData['actions'])->toHaveCount(2);
});

it('sends notification with scheduling via the channel', function (): void {
    $capturedData = null;

    stubNativephpCall(function (string $function, string $data) use (&$capturedData) {
        $capturedData = json_decode($data, true);

        return json_encode(['success' => true]);
    });

    $channel = $this->app->make(LocalNotificationChannel::class);

    $notification = new class extends Notification
    {
        public function toLocalNotification(object $notifiable): LocalNotificationMessage
        {
            return LocalNotificationMessage::create()
                ->id('schedule-channel')
                ->title('Reminder')
                ->body('Take your medicine')
                ->repeat(RepeatInterval::Daily)
                ->repeatCount(30)
                ->sound();
        }
    };

    $channel->send(new class {}, $notification);

    expect($capturedData)
        ->toHaveKey('repeat', 'daily')
        ->toHaveKey('repeatCount', 30)
        ->toHaveKey('sound', true);
});
