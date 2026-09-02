<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class GuardianForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.guardian-forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ]);

        // Probe the guardian broker; the response is identical whether or not
        // the address belongs to a guardian, so we never reveal account state.
        Password::broker('guardians')->sendResetLink(['email' => $request->email]);

        return back()->with('status', mawa_lang('auth.reset_link_sent'));
    }
}
