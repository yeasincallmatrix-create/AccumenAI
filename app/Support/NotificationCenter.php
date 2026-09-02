<?php

namespace App\Support;

use App\Models\InstituteUser;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves which notifications the current user can see and tracks their
 * read state via the `notification_reads` table.
 *
 * Visibility rules:
 *   - platform_admin  -> sees every notification (platform + institute scoped)
 *   - institute_user  -> institute-scoped notifications for their institute,
 *                        plus user-scoped notifications addressed to them
 *   - web `User`      -> treated as an institute user via their active workspace
 */
final class NotificationCenter
{
    /**
     * The reader type stored in `notification_reads.user_type`.
     */
    public static function readerType(): ?string
    {
        $user = Auth::user();

        if ($user instanceof PlatformAdmin) {
            return 'platform_admin';
        }

        if ($user instanceof InstituteUser || $user instanceof User) {
            return 'institute_user';
        }

        return null;
    }

    /**
     * The reader id stored in `notification_reads.user_id`.
     */
    public static function readerId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Institute id the current user belongs to (null for platform admins / guests).
     */
    public static function instituteId(): ?int
    {
        $user = Auth::user();

        if ($user instanceof InstituteUser) {
            return $user->institute_id;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            return $membership?->institution_id;
        }

        return null;
    }

    public static function reader(): ?array
    {
        $type = self::readerType();
        $id = self::readerId();

        if ($type === null || $id === null) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Query builder limited to the notifications visible to the current user.
     */
    public static function visibleQuery(): Builder
    {
        $query = Notification::query();

        if (self::readerType() === 'platform_admin') {
            $reader = self::reader();

            $query->where(function (Builder $q) use ($reader) {
                $q->where('scope', 'platform')
                    ->orWhere('scope', 'institute');
                if ($reader !== null) {
                    $q->orWhere(function (Builder $inner) use ($reader) {
                        $inner->where('scope', 'user')
                            ->where('target_user_type', $reader['type'])
                            ->where('target_user_id', $reader['id']);
                    });
                }
            });

            // Hide notifications the admin created themselves (their own actions).
            if ($reader !== null) {
                $query->where(function (Builder $q) use ($reader) {
                    $q->where('created_by_type', '!=', 'platform_admin')
                        ->orWhere('created_by_id', '!=', $reader['id'])
                        ->orWhereNull('created_by_type')
                        ->orWhereNull('created_by_id');
                });
            }

            return $query;
        }

        $instituteId = self::instituteId();
        $reader = self::reader();

        $query->where(function (Builder $q) use ($instituteId, $reader) {
            $applied = false;

            if ($instituteId !== null) {
                $q->orWhere(function (Builder $inner) use ($instituteId) {
                    $inner->where('scope', 'institute')->where('institute_id', $instituteId);
                });
                $applied = true;
            }

            if ($reader !== null) {
                $q->orWhere(function (Builder $inner) use ($reader) {
                    $inner->where('scope', 'user')
                        ->where('target_user_type', $reader['type'])
                        ->where('target_user_id', $reader['id']);
                });
                $applied = true;
            }

            if (! $applied) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    /**
     * Ids of the notifications already read by the current user.
     *
     * @return list<int>
     */
    public static function readIds(): array
    {
        $reader = self::reader();
        if ($reader === null) {
            return [];
        }

        return NotificationRead::query()
            ->where('user_type', $reader['type'])
            ->where('user_id', $reader['id'])
            ->pluck('notification_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function unreadCount(): int
    {
        $readIds = self::readIds();

        if ($readIds === []) {
            return self::visibleQuery()->count();
        }

        return self::visibleQuery()->whereNotIn('id', $readIds)->count();
    }

    public static function latest(int $limit = 5): Collection
    {
        return self::visibleQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function isVisible(Notification $notification): bool
    {
        if (self::readerType() === 'platform_admin') {
            // Hide notifications the admin created themselves (their own actions).
            if ($notification->created_by_type === 'platform_admin'
                && $notification->created_by_id !== null
                && (int) $notification->created_by_id === (int) self::readerId()) {
                return false;
            }

            $isPlatform = $notification->scope === 'platform';
            $isInstitute = $notification->scope === 'institute';
            $isUserForMe = $notification->scope === 'user'
                && $notification->target_user_type === 'platform_admin'
                && (int) $notification->target_user_id === (int) self::readerId();

            return $isPlatform || $isInstitute || $isUserForMe;
        }

        if ($notification->scope === 'institute') {
            return $notification->institute_id !== null
                && (int) $notification->institute_id === (int) self::instituteId();
        }

        if ($notification->scope === 'user') {
            $reader = self::reader();

            return $reader !== null
                && $notification->target_user_type === $reader['type']
                && (int) $notification->target_user_id === (int) $reader['id'];
        }

        return false;
    }

    public static function markAsRead(Notification $notification): bool
    {
        $reader = self::reader();
        if ($reader === null || ! self::isVisible($notification)) {
            return false;
        }

        NotificationRead::firstOrCreate(
            [
                'notification_id' => $notification->id,
                'user_type' => $reader['type'],
                'user_id' => $reader['id'],
            ],
            ['read_at' => now()]
        );

        return true;
    }

    /**
     * Mark every visible notification as read for the current user.
     * Returns the number of new read records created.
     */
    public static function markAllRead(): int
    {
        $reader = self::reader();
        if ($reader === null) {
            return 0;
        }

        $ids = self::visibleQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return 0;
        }

        $existing = NotificationRead::query()
            ->where('user_type', $reader['type'])
            ->where('user_id', $reader['id'])
            ->whereIn('notification_id', $ids)
            ->pluck('notification_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        foreach (array_diff($ids, $existing) as $id) {
            $rows[] = [
                'notification_id' => $id,
                'user_type' => $reader['type'],
                'user_id' => $reader['id'],
                'read_at' => now(),
            ];
        }

        if ($rows !== []) {
            NotificationRead::insert($rows);
        }

        return count($rows);
    }
}
