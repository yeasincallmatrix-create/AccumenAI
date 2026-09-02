<?php

namespace App\Services\Notification;

use App\Jobs\SendNotificationJob;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Services\Notification\Channels\InAppChannel;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator for the notification pipeline.
 *
 * Event → recipient resolution → channel eligibility (config ∩ active template
 * ∩ institute toggles ∩ user preference ∩ contact availability) → template
 * rendering → canonical log row → queued delivery job.
 *
 * Every step is defensive: an unknown event, missing template or per-recipient
 * failure is recorded, never thrown, so the engine can never break a business
 * transaction that triggered it.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRecipientResolver $resolver,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly InAppChannel $inAppChannel,
        private readonly MailChannel $mailChannel,
        private readonly SmsChannel $smsChannel,
    ) {}

    /**
     * @param  mixed  $recipients  single or list of InstituteUser|Student|User|array
     * @param  array<string, mixed>  $data  template variables + link/actor metadata
     * @param  array{institute_id?: int|null, language?: string|null, channels?: array<int,string>|null, link?: string|null, actor_type?: string|null, actor_id?: int|null}  $options
     */
    public function send(string $event, mixed $recipients, array $data = [], array $options = []): void
    {
        $events = config('notifications.events', []);
        $eventConfig = is_array($events) ? ($events[$event] ?? null) : null;
        if (! is_array($eventConfig)) {
            return;
        }

        try {
            $recipients = $this->resolver->resolveMany($recipients);
        } catch (\Throwable $e) {
            Log::warning('notification.recipient_resolution_failed', ['event' => $event, 'error' => $e->getMessage()]);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                $this->deliver($event, $eventConfig, $recipient, $data, $options);
            } catch (\Throwable $e) {
                Log::warning('notification.delivery_failed', ['event' => $event, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $eventConfig
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function deliver(array|string $event, array $eventConfig, array $recipient, array $data, array $options): void
    {
        $instituteId = $recipient['institute_id'] ?? $options['institute_id'] ?? null;
        $language = $options['language'] ?? mawa_current_lang();

        $data = $this->withDefaults($data, $instituteId);

        $channels = $options['channels'] ?? $eventConfig['channels'];
        if (! is_array($channels)) {
            $channels = ['in_app'];
        }

        foreach ($channels as $channel) {
            if (! in_array($channel, ['in_app', 'email', 'sms'], true)) {
                continue;
            }

            if (! $this->channelAllowed($channel, $instituteId)) {
                continue;
            }

            if (! $this->contactAvailable($channel, $recipient)) {
                continue;
            }

            $template = $this->resolveTemplate($event, $channel, $language, $instituteId);
            if ($template === null) {
                continue;
            }

            if ($this->prefersDisabled($recipient, $event, $channel)) {
                continue;
            }

            $subject = $this->renderer->subject($template->subject, $data);
            $body = $this->renderer->render($template->body, $data);

            if ($channel === 'in_app') {
                $subject = $subject ?: $body;
            }

            if ($channel !== 'in_app' && ! filled($body)) {
                continue;
            }

            $metadata = $data;
            $metadata['actor_type'] = $options['actor_type'] ?? 'system';
            $metadata['actor_id'] = $options['actor_id'] ?? null;
            $metadata['link'] = $options['link'] ?? null;

            $log = NotificationLog::create([
                'institute_id' => $instituteId,
                'template_id' => $template->id,
                'event' => $event,
                'recipient_type' => $recipient['recipient_type'],
                'recipient_id' => $recipient['recipient_id'],
                'recipient_contact' => $channel === 'email' ? $recipient['email'] : $recipient['phone'],
                'channel' => $channel,
                'status' => NotificationLog::STATUS_QUEUED,
                'subject' => $subject,
                'body' => $body,
                'queued_at' => now(),
                'max_retries' => max(0, (int) config('notifications.retry.max_attempts', 3) - 1),
                'retry_count' => 0,
                'metadata' => $metadata,
            ]);

            SendNotificationJob::dispatch($log->id)->onQueue(config('notifications.delivery.queue', 'notifications'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDefaults(array $data, ?int $instituteId): array
    {
        if (! array_key_exists('institute_name', $data) && $instituteId !== null) {
            $data['institute_name'] = Institute::query()->where('id', $instituteId)->value('name');
        }

        return $data;
    }

    private function channelAllowed(string $channel, ?int $instituteId): bool
    {
        // Priority: institute override → platform default → true
        if ($instituteId !== null) {
            $settings = InstituteSetting::query()->where('institute_id', $instituteId)->first();
            if ($settings !== null) {
                $toggles = $settings->notification_settings;
                if (is_array($toggles) && array_key_exists($channel, $toggles)) {
                    return (bool) $toggles[$channel];
                }
            }
        }

        // Platform fallback: notifications.email_enabled / sms_enabled
        if ($channel === 'email') {
            $platform = \App\Models\Setting::get('notifications.email_enabled');
            if ($platform !== null && $platform !== '') {
                return $platform === '1' || $platform === true || $platform === 1;
            }
        }
        if ($channel === 'sms') {
            $platform = \App\Models\Setting::get('notifications.sms_enabled');
            if ($platform !== null && $platform !== '') {
                return $platform === '1' || $platform === true || $platform === 1;
            }
        }

        return true;
    }

    public static function platformChannelEnabled(string $channel): bool
    {
        if ($channel === 'email') {
            $v = \App\Models\Setting::get('notifications.email_enabled');
            if ($v !== null && $v !== '') return $v === '1';
            return true;
        }
        if ($channel === 'sms') {
            $v = \App\Models\Setting::get('notifications.sms_enabled');
            if ($v !== null && $v !== '') return $v === '1';
            return true;
        }
        return true;
    }

    private function contactAvailable(string $channel, array $recipient): bool
    {
        if ($channel === 'in_app') {
            return in_array($recipient['recipient_type'], ['platform_admin', 'institute_user', 'student'], true);
        }

        return $channel === 'email'
            ? filled($recipient['email'])
            : filled($recipient['phone']);
    }

    private function resolveTemplate(string $event, string $channel, string $language, ?int $instituteId): ?NotificationTemplate
    {
        $query = NotificationTemplate::query()
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('language', $language)
            ->where('is_active', true);

        // Prefer the institute override; fall back to the global default.
        return $query->orderByDesc('institute_id')->first();
    }

    private function prefersDisabled(array $recipient, string $event, string $channel): bool
    {
        if ($recipient['recipient_id'] === null) {
            return false;
        }

        $base = ['recipient_type' => $recipient['recipient_type'], 'recipient_id' => $recipient['recipient_id'], 'channel' => $channel];

        $eventPref = NotificationPreference::query()->where($base)->where('event', $event)->value('enabled');
        $allPref = NotificationPreference::query()->where($base)->whereNull('event')->value('enabled');

        return in_array($eventPref, [false, 0, '0'], true)
            || in_array($allPref, [false, 0, '0'], true);
    }
}
