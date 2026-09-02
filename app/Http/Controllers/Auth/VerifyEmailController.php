<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, mixed $id, string $hash): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('institute.login');
        }

        if ((string) $user->getKey() !== (string) $id ||
            ! hash_equals((string) sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended('/')->with('status', mawa_lang('auth.email_verified'));
    }
}
