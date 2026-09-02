<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\GuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Step 47 — Guardian notifications page.
 *
 * Read-only view over the existing notifications store: institute-scoped
 * notifications of the guardian's institute plus user-scoped notifications
 * addressed to any of the guardian's linked students. Guardians never mark
 * notifications read.
 */
class GuardianNotificationController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
    ) {}

    public function index(Request $request)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $studentIds = $this->guardians->students($guardian)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $notifications = DB::table('notifications')
            ->where(function ($q) use ($guardian, $studentIds) {
                $q->where(fn ($q2) => $q2->where('scope', 'institute')->where('institute_id', $guardian->institute_id));

                if ($studentIds !== []) {
                    $q->orWhere(fn ($q2) => $q2
                        ->where('scope', 'user')
                        ->where('target_user_type', 'student')
                        ->whereIn('target_user_id', $studentIds));
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('guardian.notifications', [
            'guardian' => $guardian,
            'notifications' => $notifications,
        ]);
    }
}
