<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationJob;
use App\Models\InstituteUser;
use App\Models\NotificationLog;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Delivery log viewer with per-row retry. Tenant scoping on the model (plus an
 * explicit institute guard) keeps every log inside the caller's institute.
 */
class NotificationLogController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = $this->resolveInstituteId($request->user());

        $query = NotificationLog::query()->where('institute_id', $instituteId);

        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }
        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        return view('settings.notifications.logs', [
            'logs' => $query->latest()->paginate(25)->withQueryString(),
            'events' => config('notifications.events', []),
            'statuses' => NotificationLog::STATUSES,
            'filters' => [
                'event' => $request->query('event'),
                'channel' => $request->query('channel'),
                'status' => $request->query('status'),
                'date' => $request->query('date'),
            ],
        ]);
    }

    public function show(Request $request, NotificationLog $notificationLog): View
    {
        $this->authorizeLog($request, $notificationLog);

        return view('settings.notifications.log-show', ['log' => $notificationLog]);
    }

    public function retry(Request $request, NotificationLog $notificationLog): RedirectResponse
    {
        $this->authorizeLog($request, $notificationLog);

        if ($notificationLog->status !== NotificationLog::STATUS_FAILED || $notificationLog->retry_count >= $notificationLog->max_retries) {
            return back()->withErrors(['retry' => 'This log cannot be retried.'], 'notification_retry');
        }

        $notificationLog->forceFill([
            'status' => NotificationLog::STATUS_QUEUED,
            'queued_at' => now(),
            'failed_at' => null,
            'error' => null,
        ])->save();

        SendNotificationJob::dispatch($notificationLog->id)->onQueue(config('notifications.delivery.queue', 'notifications'));

        return back()->with('status', 'Notification re-queued for delivery.');
    }

    private function authorizeLog(Request $request, NotificationLog $log): void
    {
        $instituteId = $this->resolveInstituteId($request->user());
        abort_if($log->institute_id !== $instituteId, 403);
    }

    private function resolveInstituteId($user): ?int
    {
        if ($user instanceof InstituteUser) {
            return $user->institute_id;
        }

        if ($user instanceof User) {
            return Workspace::membership()?->institution_id;
        }

        return null;
    }
}
