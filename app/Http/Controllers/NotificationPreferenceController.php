<?php

namespace App\Http\Controllers;

use App\Models\InstituteUser;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Self-service notification preferences for the authenticated user. Only
 * disable-rows are persisted; removing the row restores the default routing.
 */
class NotificationPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $me = $this->currentRecipient($request->user());
        abort_unless($me !== null, 403);

        $events = array_keys(config('notifications.events', []));
        $disabled = $request->input('disabled', []);

        DB::transaction(function () use ($me, $events, $disabled) {
            $query = NotificationPreference::query()
                ->where('recipient_type', $me['recipient_type'])
                ->where('recipient_id', $me['recipient_id']);

            $query->delete();

            $rows = [];
            foreach ($events as $event) {
                foreach (['in_app', 'email', 'sms'] as $channel) {
                    if (! empty($disabled[$event][$channel])) {
                        $rows[] = [
                            'recipient_type' => $me['recipient_type'],
                            'recipient_id' => $me['recipient_id'],
                            'event' => $event,
                            'channel' => $channel,
                            'enabled' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            if ($rows !== []) {
                NotificationPreference::query()->insert($rows);
            }
        });

        return back()->with('status', 'Notification preferences updated.');
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
