<?php

namespace App\Http\Controllers;

use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifications hub: overview stats, institute channel master toggles and the
 * authenticated user's own delivery preferences.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = $this->resolveInstituteId($request->user());

        $setting = $instituteId !== null ? InstituteSetting::query()->where('institute_id', $instituteId)->first() : null;
        $toggles = is_array($setting?->notification_settings) ? $setting->notification_settings : [];

        $baseLogs = $instituteId !== null
            ? NotificationLog::query()->where('institute_id', $instituteId)
            : NotificationLog::query();

        $stats = [
            'total' => (clone $baseLogs)->count(),
            'queued' => (clone $baseLogs)->where('status', NotificationLog::STATUS_QUEUED)->count(),
            'sending' => (clone $baseLogs)->where('status', NotificationLog::STATUS_SENDING)->count(),
            'sent' => (clone $baseLogs)->where('status', NotificationLog::STATUS_SENT)->count(),
            'failed' => (clone $baseLogs)->where('status', NotificationLog::STATUS_FAILED)->count(),
        ];

        $me = $this->currentRecipient($request->user());

        $preferences = $me !== null
            ? NotificationPreference::query()
                ->where('recipient_type', $me['recipient_type'])
                ->where('recipient_id', $me['recipient_id'])
                ->get()
            : collect();

        $disabled = [];
        foreach ($preferences as $pref) {
            $eventKey = $pref->event ?? '*';
            $disabled[$eventKey][$pref->channel] = true;
        }

        return view('settings.notifications.index', [
            'instituteId' => $instituteId,
            'toggles' => $toggles,
            'stats' => $stats,
            'me' => $me,
            'disabled' => $disabled,
            'events' => config('notifications.events', []),
        ]);
    }

    public function updateChannels(Request $request): RedirectResponse
    {
        $instituteId = $this->resolveInstituteId($request->user());
        abort_unless($instituteId !== null, 403);

        $data = $request->validate([
            'in_app' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'sms' => ['nullable', 'boolean'],
        ]);

        InstituteSetting::updateOrCreate(
            ['institute_id' => $instituteId],
            [
                'notification_settings' => [
                    'in_app' => (bool) ($data['in_app'] ?? false),
                    'email' => (bool) ($data['email'] ?? false),
                    'sms' => (bool) ($data['sms'] ?? false),
                ],
            ]
        );

        return redirect()
            ->route('settings.notifications.index')
            ->with('status', 'Notification channel settings updated.');
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

    /**
     * @return array{recipient_type: string, recipient_id: int}|null
     */
    private function currentRecipient($user): ?array
    {
        if ($user instanceof InstituteUser) {
            return ['recipient_type' => 'institute_user', 'recipient_id' => (int) $user->id];
        }

        if ($user instanceof User) {
            return ['recipient_type' => 'platform_admin', 'recipient_id' => (int) $user->id];
        }

        return null;
    }
}
