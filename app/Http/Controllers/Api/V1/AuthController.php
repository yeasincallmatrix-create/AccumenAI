<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InstituteUser;
use App\Support\EmailNormalizer;
use App\Support\PasswordHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            app(\App\Services\Auth\PasswordService::class)->rehashIfNeeded($user, $validated['password']);
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

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

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
    private function issueToken(InstituteUser $user, string $deviceName): array
    {
        $token = $user->createToken($deviceName, [
            'institute_id:'.$user->institute_id,
            'branch_id:'.$user->branch_id,
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
            'institute_id' => $user->institute_id,
            'branch_id' => $user->branch_id,
            'role' => $user->role?->slug,
            'locale' => $user->preferred_language ?? 'en',
        ];
    }

    private function registerFailedLogin(InstituteUser $user): void
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
