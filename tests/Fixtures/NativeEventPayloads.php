<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications\Tests\Fixtures;

use Ikromjon\LocalNotifications\Events\NotificationActionPressed;
use Ikromjon\LocalNotifications\Events\NotificationReceived;
use Ikromjon\LocalNotifications\Events\NotificationScheduled;
use Ikromjon\LocalNotifications\Events\NotificationTapped;
use Ikromjon\LocalNotifications\Events\NotificationUpdated;
use Ikromjon\LocalNotifications\Events\PermissionDenied;
use Ikromjon\LocalNotifications\Events\PermissionGranted;

/**
 * Every payload the native code posts to `/_native/api/events`, as
 * [event class, payload] pairs, transcribed from the dispatch sites:
 *
 * iOS (resources/ios/Sources/LocalNotificationsFunctions.swift)
 * - LocalNotificationDelegate willPresent                    NotificationReceived
 * - LocalNotificationDelegate didReceive, default action     NotificationTapped
 * - LocalNotificationDelegate didReceive, custom action      NotificationActionPressed
 * - Schedule (standard and day-of-week)                      NotificationScheduled
 * - Update                                                   NotificationUpdated
 * - RequestPermission                                        PermissionGranted, PermissionDenied
 *
 * Android (resources/android/src)
 * - LocalNotificationReceiver.onReceive                      NotificationReceived
 * - NotificationTapReceiver.onReceive, and the cold-start,
 *   warm-start and pending-queue paths in
 *   LocalNotificationsFunctions                              NotificationTapped
 * - NotificationActionReceiver.onReceive                     NotificationActionPressed
 * - NotificationScheduler.dispatchNotificationEvent          NotificationScheduled, NotificationUpdated
 * - LocalNotificationsFunctions.RequestPermission            PermissionGranted
 *
 * NativePHP builds the event with the payload as named constructor arguments,
 * so a key the constructor does not declare throws "Unknown named parameter" on
 * nativephp/mobile 3.3.x and is dropped on 4.6.1+. Keys a site only sometimes
 * sends get their own entry, so the minimal payload shows every required
 * parameter is sent and the fuller ones that every key is declared.
 *
 * Change this list whenever a native dispatch site changes.
 */
final class NativeEventPayloads
{
    /**
     * @return array<string, array{0: class-string, 1: array<string, mixed>}>
     */
    public static function all(): array
    {
        $data = ['habit_id' => 42, 'screen' => 'habits/42'];

        return [
            'NotificationScheduled' => [NotificationScheduled::class, [
                'id' => 'water-reminder',
                'title' => 'Drink water',
                'body' => 'Time for a glass of water',
            ]],
            'NotificationUpdated' => [NotificationUpdated::class, [
                'id' => 'water-reminder',
                'title' => 'Drink more water',
                'body' => 'Two glasses this time',
            ]],
            'NotificationReceived' => [NotificationReceived::class, [
                'id' => 'water-reminder',
                'title' => 'Drink water',
                'body' => 'Time for a glass of water',
            ]],
            'NotificationReceived with data' => [NotificationReceived::class, [
                'id' => 'water-reminder',
                'title' => 'Drink water',
                'body' => 'Time for a glass of water',
                'data' => $data,
            ]],
            'NotificationTapped' => [NotificationTapped::class, [
                'id' => 'water-reminder',
                'title' => 'Drink water',
                'body' => 'Time for a glass of water',
            ]],
            'NotificationTapped with data' => [NotificationTapped::class, [
                'id' => 'water-reminder',
                'title' => 'Drink water',
                'body' => 'Time for a glass of water',
                'data' => $data,
            ]],
            'NotificationActionPressed' => [NotificationActionPressed::class, [
                'notificationId' => 'water-reminder',
                'actionId' => 'done',
            ]],
            'NotificationActionPressed with data and text input' => [NotificationActionPressed::class, [
                'notificationId' => 'water-reminder',
                'actionId' => 'reply',
                'data' => $data,
                'inputText' => 'Already had one',
            ]],
            'NotificationActionPressed from a snooze action' => [NotificationActionPressed::class, [
                'notificationId' => 'water-reminder',
                'actionId' => 'snooze',
                'data' => $data,
                'snoozed' => true,
                'snoozeSeconds' => 300,
            ]],
            'PermissionGranted' => [PermissionGranted::class, []],
            // iOS only: Android requests the permission but never reports a denial.
            'PermissionDenied' => [PermissionDenied::class, []],
        ];
    }
}
