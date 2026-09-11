<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Phase 07: reader enum value for the token's user. Derived from the
     * request user (token context), NOT Auth::user() (default web guard),
     * and never get_class() output — notification_reads.user_type is an
     * ENUM shared with the web NotificationCenter paths.
     */
    private function readerType(Request $request): ?string
    {
        $user = $request->user();

        if ($user instanceof \App\Models\PlatformAdmin) {
            return 'platform_admin';
        }
        if ($user instanceof \App\Models\InstituteUser) {
            return 'institute_user';
        }

        return \App\Support\NotificationCenter::readerType();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('institute_id', $user->institute_id)
                    ->orWhereNull('institute_id');
            });

        if ($request->filled('unread_only') && $request->boolean('unread_only')) {
            $readerType = $this->readerType($request);
            $readIds = NotificationRead::where('user_type', $readerType)
                ->where('user_id', $user->id)
                ->pluck('notification_id');

            $query->whereDoesntHave('reads', function ($q) use ($user, $readerType) {
                $q->where('user_type', $readerType)
                    ->where('user_id', $user->id);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $notifications = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedResponse($notifications);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Phase 07: read receipts are per-user integrity state — a token may
        // only acknowledge notifications visible to its own institute scope
        // (same rule as index: own institute or global broadcast). Anything
        // else is 404, never an oracle or a cross-tenant write.
        $notification = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('institute_id', $user->institute_id)
                    ->orWhereNull('institute_id');
            })
            ->find($id);

        if (! $notification) {
            return $this->notFoundResponse('Notification not found.');
        }

        // Unmappable actor types cannot own read state.
        if ($this->readerType($request) === null) {
            return $this->notFoundResponse('Notification not found.');
        }

        NotificationRead::updateOrCreate(
            [
                'notification_id' => $id,
                // Phase 07: established enum mapping — never get_class()
                // output (the column is an ENUM and reads filter on these
                // same values).
                'user_type' => $this->readerType($request),
                'user_id' => $user->id,
            ],
            ['read_at' => now()]
        );

        return $this->successResponse(null, 'Notification marked as read.');
    }
}
