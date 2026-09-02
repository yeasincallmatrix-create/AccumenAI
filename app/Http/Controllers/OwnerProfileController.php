<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\Workspace;
use App\Services\Auth\PasswordService;
use App\Support\PasswordHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $membership = $user instanceof User ? Workspace::membership() : null;

        $instituteId = $user instanceof InstituteUser
            ? $user->institute_id
            : ($membership?->institution_id);

        $institute = $instituteId ? Institute::with('package', 'country')->find($instituteId) : null;

        $roleLabel = match (true) {
            $user instanceof InstituteUser => $user->role?->name ?? 'Staff',
            $user instanceof User && $membership !== null => $membership->role?->name ?? 'Staff',
            default => '',
        };

        return view('owner.profile', [
            'user' => $user,
            'institute' => $institute,
            'membership' => $membership,
            'roleLabel' => $roleLabel,
            'preferredLanguage' => $user->preferred_language ?? 'en',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'language' => ['required', 'in:en,bn'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'preferred_language' => $data['language'],
        ])->save();

        session(['mawa_lang' => $data['language']]);

        return redirect()->route('owner.profile')->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        app(PasswordService::class)->changePassword($user, $data['current_password'], $data['password']);

        return redirect()->route('owner.profile')->with('status', 'Password updated.');
    }
}
