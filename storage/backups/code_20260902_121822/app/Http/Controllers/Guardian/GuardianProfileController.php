<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\Auth\PasswordService;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use App\Support\PasswordHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Step 47 — Guardian profile page: read-only details plus the guardian's own
 * password change (the only mutating action a guardian may perform).
 */
class GuardianProfileController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly GuardianAuditService $audit,
    ) {}

    public function show()
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        return view('guardian.profile', [
            'guardian' => $guardian,
            'students' => $this->guardians->students($guardian),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        try {
            app(PasswordService::class)->changePassword($guardian, (string) $request->input('current_password'), (string) $request->input('password'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Map current_password error to guardian translation
            $errors = $e->errors();
            if (isset($errors['current_password'])) {
                return back()->withErrors(['current_password' => mawa_lang('guardian.current_password_wrong')]);
            }
            throw $e;
        }

        $this->audit->record((int) $guardian->institute_id, (int) $guardian->getKey(), 'guardian_changed_password');

        return back()->with('status', mawa_lang('guardian.password_updated'));
    }
}
