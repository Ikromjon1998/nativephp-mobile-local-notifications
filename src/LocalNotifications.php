<?php

declare(strict_types=1);

namespace Ikromjon\LocalNotifications;

use Ikromjon\LocalNotifications\Contracts\LocalNotificationsInterface;
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Enums\BridgeFunction;
use Ikromjon\LocalNotifications\Enums\NotificationPriority;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;
use Ikromjon\LocalNotifications\Support\Config;
use Ikromjon\LocalNotifications\Validation\NotificationValidator;

class LocalNotifications implements LocalNotificationsInterface
{
    /**
     * Registered payload transformers, applied in order to the content of
     * schedule() and update() calls immediately before dispatch.
     *
     * @var array<int, callable(array<string, mixed>): array<string, mixed>>
     */
    protected array $transformers = [];

    /**
     * Schedule a local notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function schedule(NotificationOptions|array $options): array
    {
        $data = $options instanceof NotificationOptions
            ? $options->toArray()
            : $this->normalizeOptions($options);

        return $this->call(BridgeFunction::Schedule, $this->applyTransformers($data));
    }

    /**
     * Cancel a scheduled notification by its identifier.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->call(BridgeFunction::Cancel, ['id' => $id]);
    }

    /**
     * Cancel all scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function cancelAll(): array
    {
        return $this->call(BridgeFunction::CancelAll);
    }

    /**
     * Get a list of all pending scheduled notifications.
     *
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->call(BridgeFunction::GetPending);
    }

    /**
     * Request permission to show notifications.
     *
     * Pass $critical = true to also request iOS critical-alert authorization —
     * required for priority "urgent" to break through Do Not Disturb. Only use
     * it when the app holds Apple's critical-alerts entitlement. Ignored on
     * Android.
     *
     * @return array<string, mixed>
     */
    public function requestPermission(bool $critical = false): array
    {
        return $this->call(
            BridgeFunction::RequestPermission,
            $critical ? ['critical' => true] : [],
        );
    }

    /**
     * Check current notification permission status.
     *
     * @return array<string, mixed>
     */
    public function checkPermission(): array
    {
        return $this->call(BridgeFunction::CheckPermission);
    }

    /**
     * Update an existing scheduled notification.
     *
     * @param  NotificationOptions|array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $id, NotificationOptions|array $options): array
    {
        // The id parameter bypasses the options array, so validate it
        // explicitly — reserved sub-notification ids must be rejected here too.
        NotificationValidator::validate(['id' => $id]);

        $data = $options instanceof NotificationOptions
            ? $options->toArray()
            : $this->normalizeOptions($options);

        $data['id'] = $id;

        return $this->call(BridgeFunction::Update, $this->applyTransformers($data));
    }

    /**
     * Register a payload transformer.
     *
     * The callback receives the fully-normalized notification payload — title,
     * body, subtitle, bigText, actions, and so on — immediately before it is
     * dispatched to the native layer, and returns the payload to send. It is
     * the extension point for cross-cutting content changes such as
     * localization: translate the text once here instead of at every call site.
     *
     * Transformers run in registration order, each receiving the previous one's
     * output (a pipeline). They apply only to schedule() and update() — the
     * content-bearing calls — and never receive the internal `_config` block.
     * They run after validation and are meant for content: avoid altering
     * structural fields such as `id`, `at`, or `repeat`, which are not
     * re-validated afterwards.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $transformer
     */
    public function transformUsing(callable $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    /**
     * Remove all registered payload transformers.
     */
    public function flushTransformers(): self
    {
        $this->transformers = [];

        return $this;
    }

    /**
     * Run the registered transformers over a notification payload.
     *
     * Applied to schedule() and update() content before `_config` is injected,
     * so transformers only ever see the developer-facing payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyTransformers(array $data): array
    {
        foreach ($this->transformers as $transformer) {
            $data = $transformer($data);
        }

        return $data;
    }

    /**
     * Normalize a raw options array, converting enum values to strings and validating.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    protected function normalizeOptions(array $options): array
    {
        if (isset($options['repeat']) && $options['repeat'] instanceof RepeatInterval) {
            $options['repeat'] = $options['repeat']->value;
        }

        if (isset($options['priority']) && $options['priority'] instanceof NotificationPriority) {
            $options['priority'] = $options['priority']->value;
        }

        NotificationValidator::validate($options);

        return $options;
    }

    /**
     * Build the config values to pass to the native layer.
     *
     * @return array<string, mixed>
     */
    protected function nativeConfig(): array
    {
        return [
            'channel_id' => Config::get('channel_id', 'nativephp_local_notifications'),
            'channel_name' => Config::get('channel_name', 'Local Notifications'),
            'channel_description' => Config::get('channel_description', 'Notifications scheduled by the app'),
            'max_actions' => Config::get('max_actions', 3),
            'default_sound' => Config::get('default_sound', true),
            'tap_detection_delay_ms' => Config::get('tap_detection_delay_ms', 500),
            'navigation_replay_duration_ms' => Config::get('navigation_replay_duration_ms', 15000),
        ];
    }

    /**
     * Make a bridge call to the native layer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function call(BridgeFunction $function, array $data = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        // Inject config on every bridge call so native code can apply
        // settings (e.g. tap detection delay) before the first schedule().
        $data['_config'] = $this->nativeConfig();

        $payload = json_encode($data);

        if ($payload === false) {
            if (function_exists('logger')) {
                logger()->warning(
                    'LocalNotifications: failed to encode bridge payload: '.json_last_error_msg(),
                );
            }

            return [];
        }

        $result = nativephp_call($function->value, $payload);

        if (! $result) {
            return [];
        }

        return json_decode($result, true) ?? [];
    }
}
