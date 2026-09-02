<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Step 47 — Switch the guardian's active student (session only).
 */
class GuardianStudentSwitchController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $switched = $this->guardians->switchActiveStudent($guardian, (int) $request->input('student_id'));

        if (! $switched) {
            abort(404);
        }

        return redirect()->route('guardian.dashboard');
    }
}
