<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantDriveConnection;
use App\Support\GoogleDriveScopes;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Illuminate\Http\Request;

class DriveConnectionController extends Controller
{
    public function status()
    {
        $conn = TenantDriveConnection::where('tenant_id', auth()->user()->institute_id)
            ->whereNull('revoked_at')
            ->first();

        return response()->json([
            'connected'    => (bool) $conn,
            'email'        => $conn?->google_user_email,
            'last_sync_at' => $conn?->last_sync_at?->toIso8601String(),
        ]);
    }

    /**
     * Redirect to Google OAuth for Drive consent.
     * Scopes: drive.file (App Folder only) + userinfo.email/profile — see
     * GoogleDriveScopes::ALL; the SAME list must be used by callback().
     */
    public function connect(Request $request)
    {
        $client = $this->makeClient();
        $client->setRedirectUri($this->redirectUri());
        $client->addScope($this->scopes());
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // force refresh_token

        return redirect($client->createAuthUrl());
    }

    /**
     * Handle OAuth callback. Registered OUTSIDE the hardened group (G4):
     * Google redirects here without tenant context; auth is enough to know
     * which owner performed the connection.
     */
    public function callback(Request $request)
    {
        if (!$request->code) {
            return redirect()->route('tenant.backup.index')
                ->withErrors(['error' => 'Drive authorization failed.']);
        }

        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login')
                ->withErrors(['error' => 'Session expired during Drive authorization. Please reconnect.']);
        }

        $client = $this->makeClient();
        $client->setRedirectUri($this->redirectUri());
        $client->addScope($this->scopes());

        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (isset($token['error'])) {
            return redirect()->route('tenant.backup.index')
                ->withErrors(['error' => 'Drive authorization failed: ' . $token['error']]);
        }

        if (!isset($token['refresh_token'])) {
            return redirect()->route('tenant.backup.index')
                ->withErrors(['error' => 'No refresh token. Revoke app access in Google and retry.']);
        }

        // Get user info
        $client->setAccessToken($token);
        $oauth2 = new Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => $user->institute_id],
            [
                'connected_by_user_id' => $user->id,
                'google_user_email'    => $userInfo->email,
                'google_user_id'       => (string) $userInfo->id,
                'refresh_token'        => $token['refresh_token'],
                'drive_folder_id'      => null,
                'app_folder_id'        => null,
                'chunks_folder_id'     => null,
                'manifests_folder_id'  => null,
                'trash_folder_id'      => null,
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );

        return redirect()->route('tenant.backup.index')
            ->with('success', 'Google Drive connected.');
    }

    public function disconnect()
    {
        TenantDriveConnection::where('tenant_id', auth()->user()->institute_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return back()->with('success', 'Google Drive disconnected.');
    }

    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        return $client;
    }

    /**
     * G3: explicit GOOGLE_DRIVE_REDIRECT_URI wins (it must match an entry in
     * the Google Console); fall back to the route for local/dev setups.
     */
    private function redirectUri(): string
    {
        return config('services.google.drive_redirect') ?: route('tenant.backup.drive.callback');
    }

    /**
     * Single source of truth for OAuth scopes — never diverge between
     * connect() and callback() (caused the userinfo 401 UNAUTHENTICATED).
     */
    private function scopes(): array
    {
        return GoogleDriveScopes::ALL;
    }
}
