<?php

namespace App\Services\Notification;

use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\User;
use InvalidArgumentException;

/**
 * Turns a recipient spec into deliverable contact information.
 *
 * Recipients are always resolved from source-of-truth records (InstituteUser,
 * Student, platform User) or an explicit external email/phone. The institute id
 * is derived from the record — never from request input.
 *
 * @phpstan-type ResolvedRecipient array{
 *   recipient_type: string,
 *   recipient_id: int|null,
 *   email: string|null,
 *   phone: string|null,
 *   name: string|null,
 *   institute_id: int|null,
 * }
 */
class NotificationRecipientResolver
{
    /**
     * @return ResolvedRecipient
     */
    public function resolve(mixed $recipient): array
    {
        if ($recipient instanceof InstituteUser) {
            return [
                'recipient_type' => 'institute_user',
                'recipient_id' => (int) $recipient->id,
                'email' => $recipient->email,
                'phone' => $recipient->phone,
                'name' => trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')) ?: $recipient->email,
                'institute_id' => (int) $recipient->institute_id,
            ];
        }

        if ($recipient instanceof Student) {
            return [
                'recipient_type' => 'student',
                'recipient_id' => (int) $recipient->id,
                'email' => $recipient->email,
                // SMS goes to the guardian when present, otherwise the student phone.
                'phone' => filled($recipient->guardian_phone) ? $recipient->guardian_phone : $recipient->phone,
                'name' => $recipient->full_name ?: $recipient->student_id_number,
                'institute_id' => (int) $recipient->institute_id,
            ];
        }

        if ($recipient instanceof User) {
            return [
                'recipient_type' => 'platform_admin',
                'recipient_id' => (int) $recipient->id,
                'email' => $recipient->email,
                'phone' => $recipient->phone,
                'name' => $recipient->name ?? $recipient->email,
                'institute_id' => null,
            ];
        }

        if (is_array($recipient) && isset($recipient['recipient_type'])) {
            // Pre-resolved recipient (used by internal callers / tests).
            return $recipient;
        }

        if (is_array($recipient) && filled($recipient['email'] ?? null)) {
            return [
                'recipient_type' => 'external_email',
                'recipient_id' => null,
                'email' => $recipient['email'],
                'phone' => null,
                'name' => $recipient['name'] ?? null,
                'institute_id' => isset($recipient['institute_id']) ? (int) $recipient['institute_id'] : null,
            ];
        }

        if (is_array($recipient) && filled($recipient['phone'] ?? null)) {
            return [
                'recipient_type' => 'external_phone',
                'recipient_id' => null,
                'email' => null,
                'phone' => $recipient['phone'],
                'name' => $recipient['name'] ?? null,
                'institute_id' => isset($recipient['institute_id']) ? (int) $recipient['institute_id'] : null,
            ];
        }

        throw new InvalidArgumentException('Unsupported notification recipient spec.');
    }

    /**
     * @param  iterable<mixed>|mixed  $recipients
     * @return array<int, ResolvedRecipient>
     */
    public function resolveMany(mixed $recipients): array
    {
        $list = is_iterable($recipients) ? [...$recipients] : [$recipients];

        $resolved = [];
        foreach ($list as $recipient) {
            $resolved[] = $this->resolve($recipient);
        }

        return $resolved;
    }
}
