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

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('institute_id', $user->institute_id)
                    ->orWhereNull('institute_id');
            });

        if ($request->filled('unread_only') && $request->boolean('unread_only')) {
            $readIds = NotificationRead::where('user_type', get_class($user))
                ->where('user_id', $user->id)
                ->pluck('notification_id');

            $query->whereDoesntHave('reads', function ($q) use ($user) {
                $q->where('user_type', get_class($user))
                    ->where('user_id', $user->id);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $notifications = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedResponse($notifications);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (! $notification) {
            return $this->notFoundResponse('Notification not found.');
        }

        $user = $request->user();

        NotificationRead::updateOrCreate(
            [
                'notification_id' => $id,
                'user_type' => get_class($user),
                'user_id' => $user->id,
            ],
            ['read_at' => now()]
        );

        return $this->successResponse(null, 'Notification marked as read.');
    }
}
