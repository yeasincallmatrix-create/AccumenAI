<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\User;
use App\Services\Auth\EmailBanService;
use App\Services\Auth\PasswordService;
use App\Support\EmailNormalizer;
use App\Support\PasswordHash;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Mobile v1 auth. Mirrors the security posture of the web-adjacent
 * Api\AuthController (status gate, lockout, hash-integrity, verification)
 * but answers with the v1 envelope and a 90-day rolling token.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:120',
            'institute_id' => 'sometimes|integer|exists:institutes,id',
        ]);

        $email = EmailNormalizer::normalize($validated['email']) ?? $validated['email'];

        $query = InstituteUser::where('email', $email);
        if (isset($validated['institute_id'])) {
            $query->where('institute_id', $validated['institute_id']);
        }
        $user = $query->first();

        if (! $user) {
            // Not a legacy per-institute account (institute_users): fall through
            // to the global account (users — owner/staff) so both populations
            // can sign in with the same credentials and envelope.
            return $this->loginGlobalAccount($validated, $email);
        }

        if (! $user || $user->status !== 'active') {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if (! PasswordHash::looksValid((string) $user->getAuthPassword())) {
            report(sprintf('v1 login blocked: corrupted password_hash for institute_user #%s (%s)', $user->getKey(), $user->email));

            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if ($user->isLocked()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Account is locked. Try again later.', 423);
        }

        if (! PasswordHash::safeCheck($validated['password'], (string) $user->getAuthPassword())) {
            $this->registerFailedLogin($user);

            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return ApiResponse::error('FORBIDDEN', 'Please verify your email address before logging in.', 403);
        }

        try {
            app(PasswordService::class)->rehashIfNeeded($user, $validated['password']);
        } catch (\Throwable $e) {
            report($e);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $issued = $this->issueToken($user, $validated['device_name']);

        return ApiResponse::success([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    /**
     * Google sign-in for the mobile app: the client hands over the ID token
     * google_sign_in() obtained, Google's tokeninfo endpoint vouches for the
     * signature/issuer/expiry and we check the audience + verified email.
     *
     * Account rules mirror the web callback but stop at sign-in — mobile has
     * no registration/onboarding flow, so an unknown email is refused.
     */
    public function googleLogin(Request $request, EmailBanService $banService): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string|max:4096',
            'device_name' => 'required|string|max:120',
            'institute_id' => 'sometimes|integer|exists:institutes,id',
        ]);

        $audiences = array_values(array_filter([
            config('services.google.client_id'),
            config('services.google.mobile_client_id'),
        ]));

        if ($audiences === []) {
            return ApiResponse::error('GOOGLE_NOT_CONFIGURED', 'Google sign-in is not configured.', 501);
        }

        $claims = $this->googleClaims($validated['id_token']);

        if ($claims === null) {
            return ApiResponse::error('GOOGLE_AUTH_FAILED', 'Google sign-in failed.', 401);
        }

        $email = EmailNormalizer::normalize((string) ($claims['email'] ?? ''));
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === null
            || $email === ''
            || ! in_array((string) ($claims['aud'] ?? ''), $audiences, true)
            || ! $verified
            || (int) ($claims['exp'] ?? 0) < time()) {
            return ApiResponse::error('GOOGLE_AUTH_FAILED', 'Google sign-in failed.', 401);
        }

        if ($banService->isBanned($email)) {
            report(sprintf('Banned email attempted mobile Google login: %s', $email));

            return ApiResponse::error('FORBIDDEN', 'This email has been banned. Please contact support.', 403);
        }

        $account = User::withTrashed()->where('email', $email)->first();

        if ($account === null) {
            return ApiResponse::error(
                'GOOGLE_NO_ACCOUNT',
                'No account exists for this Google email. Please create one first.',
                403
            );
        }

        if ($account->trashed()) {
            return ApiResponse::error('ACCOUNT_SUSPENDED', 'Your account has been suspended. Please contact support.', 403);
        }

        if ($account->status !== 'active') {
            return ApiResponse::error('UNAUTHENTICATED', 'Your account is inactive. Please contact your administrator.', 401);
        }

        if ($account->isLocked()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Account is locked. Try again later.', 423);
        }

        // Same linking as the web callback: remember Google identity, avatar
        // and treat Google's verified email as verified here too.
        $updates = [];
        if (empty($account->google_id) && ! empty($claims['sub'])) {
            $updates['google_id'] = $claims['sub'];
        }
        if (empty($account->avatar) && ! empty($claims['picture'])) {
            $updates['avatar'] = $claims['picture'];
        }
        if (empty($account->email_verified_at)) {
            $updates['email_verified_at'] = now();
        }
        if ($updates !== []) {
            $account->update($updates);
        }

        $membership = Workspace::membershipForToken($account, $validated['institute_id'] ?? null);

        if ($membership === null) {
            return ApiResponse::error('NO_WORKSPACE', 'No active institute workspace.', 403);
        }

        $account->forceFill([
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $issued = $this->issueToken($account, $validated['device_name'], $membership);

        return ApiResponse::success([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'user' => self::globalUserPayload($account, $membership),
        ]);
    }

    /**
     * Verify an ID token with Google and return its claims, or null when
     * Google rejects it (bad signature, wrong issuer, expired).
     */
    private function googleClaims(string $idToken): ?array
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $claims = $response->json();

        if (! is_array($claims)) {
            return null;
        }

        $issuer = (string) ($claims['iss'] ?? '');
        if ($issuer !== 'accounts.google.com' && $issuer !== 'https://accounts.google.com') {
            return null;
        }

        return $claims;
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            // Global account: re-pin the same institution the old token carried.
            $membership = $this->membershipForToken($user);

            if ($membership === null) {
                return ApiResponse::unauthorized();
            }

            $user->currentAccessToken()?->delete();
            $issued = $this->issueToken($user, 'refresh', $membership);

            return ApiResponse::success([
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ]);
        }

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $request->user()->currentAccessToken()->delete();

        $issued = $this->issueToken($user, 'refresh');

        return ApiResponse::success([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null);
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    private function issueToken(InstituteUser|User $user, string $deviceName, ?Membership $membership = null): array
    {
        $instituteId = $user instanceof InstituteUser
            ? $user->institute_id
            : $membership?->institution_id;
        $branchId = $user instanceof InstituteUser
            ? $user->branch_id
            : $membership?->branch_id;

        $token = $user->createToken($deviceName, [
            'institute_id:'.$instituteId,
            'branch_id:'.$branchId,
        ]);

        $expiresAt = now()->addDays(90);
        $token->accessToken->forceFill(['expires_at' => $expiresAt])->save();

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function userPayload(InstituteUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => null,
            'institute_id' => $user->institute_id,
            'branch_id' => $user->branch_id,
            'role' => $user->role?->slug,
            'locale' => $user->preferred_language ?? 'en',
        ];
    }

    /**
     * Same envelope for a global (users) account, whose institute/role live on
     * the membership row instead of the account itself.
     *
     * @return array<string, mixed>
     */
    public static function globalUserPayload(User $user, Membership $membership): array
    {
        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : (string) $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar,
            'institute_id' => (int) $membership->institution_id,
            'branch_id' => $membership->branch_id,
            'role' => $membership->role?->slug,
            'locale' => $user->preferred_language ?? 'en',
        ];
    }

    /**
     * Sign-in path for global accounts (owners + staff in `users`), mirroring
     * the legacy-branch checks: status, hash integrity, lockout, verification.
     */
    private function loginGlobalAccount(array $validated, string $email): JsonResponse
    {
        $account = User::query()->where('email', $email)->first();

        if (! $account || $account->status !== 'active') {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if (! PasswordHash::looksValid((string) $account->getAuthPassword())) {
            report(sprintf('v1 login blocked: corrupted password_hash for user #%s (%s)', $account->getKey(), $account->email));

            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if ($account->isLocked()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Account is locked. Try again later.', 423);
        }

        if (! PasswordHash::safeCheck($validated['password'], (string) $account->getAuthPassword())) {
            $this->registerFailedLogin($account);

            return ApiResponse::error('UNAUTHENTICATED', 'Invalid credentials.', 401);
        }

        if (! $account->hasVerifiedEmail()) {
            return ApiResponse::error('FORBIDDEN', 'Please verify your email address before logging in.', 403);
        }

        try {
            app(PasswordService::class)->rehashIfNeeded($account, $validated['password']);
        } catch (\Throwable $e) {
            report($e);
        }

        $membership = Workspace::membershipForToken($account, $validated['institute_id'] ?? null);

        if ($membership === null) {
            return ApiResponse::error('NO_WORKSPACE', 'No active institute workspace.', 403);
        }

        $account->forceFill([
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $issued = $this->issueToken($account, $validated['device_name'], $membership);

        return ApiResponse::success([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'user' => self::globalUserPayload($account, $membership),
        ]);
    }

    /**
     * Institution pinned on the caller's token (first active membership when a
     * request carries no abilities, e.g. test-transient tokens).
     */
    private function membershipForToken(User $user): ?Membership
    {
        $token = $user->currentAccessToken();
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        return Workspace::activeMembershipFor($user, $abilities);
    }

    private function registerFailedLogin(InstituteUser|User $user): void
    {
        $count = (int) $user->failed_login_count + 1;
        $data = ['failed_login_count' => $count];

        if ($user->locked_until !== null && ! $user->isLocked()) {
            $count = 1;
            $data = ['failed_login_count' => 1, 'locked_until' => null];
        }

        if ($count >= 5) {
            $data['locked_until'] = now()->addMinutes(15);
        }

        $user->forceFill($data)->save();
    }
}
