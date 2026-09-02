<?php

namespace App\Services\Notification\Channels;

use App\Mail\NotificationMail;
use App\Models\NotificationLog;
use App\Services\Notification\ResolveMailer;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a notification via email.
 *
 * Uses the institute/platform resolved SMTP configuration (see ResolveMailer)
 * by registering a dedicated runtime mailer, so it never mutates the
 * application default mailer used elsewhere.
 */
class MailChannel implements NotificationChannelContract
{
    public function __construct(private readonly ResolveMailer $resolveMailer) {}

    public function send(NotificationLog $log, array $data = []): array
    {
        $email = $log->recipient_contact ?: ($data['email'] ?? null);
        if (! filled($email)) {
            return $this->result('skipped');
        }

        try {
            $mailerName = $this->registerMailer($log->institute_id);

            $mailable = new NotificationMail($log->subject ?? '', $log->body ?? '');

            if ($mailerName !== null) {
                Mail::mailer($mailerName)->to($email)->send($mailable);
            } else {
                Mail::to($email)->send($mailable);
            }

            return $this->result('sent', 'smtp');
        } catch (\Throwable $e) {
            return $this->result('failed', 'smtp', null, null, substr($e->getMessage(), 0, 500));
        }
    }

    private function registerMailer(?int $instituteId): ?string
    {
        $config = $this->resolveMailer->resolve($instituteId);
        if ($config === null) {
            return null;
        }

        $name = 'notification_smtp';

        config([
            "mail.mailers.{$name}" => [
                'transport' => 'smtp',
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
                'password' => $config['password'],
                'encryption' => $config['encryption'],
                'timeout' => 30,
            ],
            'mail.from.address' => $config['from_address'],
            'mail.from.name' => $config['from_name'],
        ]);

        return $name;
    }

    /**
     * @return array{status: string, provider: string|null, provider_message_id: null, provider_response: null, error: string|null}
     */
    private function result(string $status, ?string $provider = null, mixed $messageId = null, mixed $response = null, ?string $error = null): array
    {
        return [
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId,
            'provider_response' => $response,
            'error' => $error,
        ];
    }
}
