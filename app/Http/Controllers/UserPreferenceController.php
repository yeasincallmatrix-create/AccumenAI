<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Per-account UI preferences. Every value written here lives in the
 * authenticated account's own `preferences` column, so it never affects
 * any other user.
 */
class UserPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        $user = Auth::user();

        return view('account.preferences', [
            'user' => $user,
            'preferredLanguage' => $user->preferred_language,
            'preferences' => $user->allPreferences(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:default,light,dark'],
            'language' => ['required', 'in:en,bn'],
        ]);

        $user = Auth::user();

        $user->forceFill(['preferred_language' => $data['language']])->save();
        $user->setPreference('theme', $data['theme']);

        // Reflect the new language immediately (session wins over the DB on page render).
        session(['mawa_lang' => $data['language']]);

        return redirect()
            ->route('account.preferences')
            ->with('status', __('preferences.saved'));
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        Auth::user()->setPreference('theme', $data['theme']);

        return response()->json(['ok' => true, 'theme' => $data['theme']]);
    }
}
