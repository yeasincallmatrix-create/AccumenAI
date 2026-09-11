<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\BranchResource;
use App\Http\Resources\InstituteResource;
use App\Http\Resources\UserResource;
use App\Models\InstituteUser;
use App\Support\EmailNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\PasswordHash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'institute_id' => 'required|integer|exists:institutes,id',
        ]);

        $normalizedEmail = EmailNormalizer::normalize((string) $request->email);

        $user = InstituteUser::where('email', $normalizedEmail ?? $request->email)
            ->where('institute_id', $request->institute_id)
            ->first();

        if (! $user) {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        // Phase 07: mirror the web login controllers — inactive accounts
        // authenticate nowhere. Generic message preserves the no-enumeration
        // property (indistinguishable from bad credentials).
        if ($user->status !== 'active') {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        if (! PasswordHash::looksValid((string) $user->getAuthPassword())) {
            report(sprintf('login blocked: corrupted password_hash for institute_user #%s (%s)', $user->getKey(), $user->email));
            return $this->errorResponse('Invalid credentials.', 401);
        }

        // Phase 07: lockout is evaluated BEFORE password verification so a
        // locked account reveals nothing further and cannot be probed.
        if ($user->isLocked()) {
            return $this->errorResponse('Account is locked. Try again later.', 423);
        }

        if (! PasswordHash::safeCheck($request->password, (string) $user->getAuthPassword())) {
            $this->registerFailedLogin($user);

            return $this->errorResponse('Invalid credentials.', 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->errorResponse('Please verify your email address before logging in.', 403);
        }

        try {
            app(\App\Services\Auth\PasswordService::class)->rehashIfNeeded($user, (string) $request->password);
        } catch (\Throwable $e) {
            report($e);
        }

        // NOTE: failed_login_count / locked_until / last_login_at are
        // intentionally NOT in $fillable (mass-assignment hardening) — system
        // writes below use forceFill, the established pattern for these.
        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $token = $user->createToken('mobile-app', [
            'institute_id:'.$user->institute_id,
            'branch_id:'.$user->branch_id,
        ]);

        return $this->successResponse([
            'user' => new UserResource($user->fresh()),
            'token' => $token->plainTextToken,
            'institute' => new InstituteResource($user->institute),
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * Phase 07 — per-account brute-force policy: 5 failures lock the
     * account for 15 minutes. Complements (does not replace) the
     * throttle:10,1 IP rate limit on the route: throttling stops IP-wide
     * floods, this stops slow targeted guessing that stays under it.
     * Counters live on the user row so they survive process restarts.
     */
    private function registerFailedLogin(InstituteUser $user): void
    {
        $count = (int) $user->failed_login_count + 1;
        $data = ['failed_login_count' => $count];

        // An expired lock starts a fresh cycle instead of stacking forever.
        if ($user->locked_until !== null && ! $user->isLocked()) {
            $count = 1;
            $data = ['failed_login_count' => 1, 'locked_until' => null];
        }

        if ($count >= 5) {
            $data['locked_until'] = now()->addMinutes(15);
        }

        $user->forceFill($data)->save();
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse(new UserResource($user));
    }

    public function institute(Request $request): JsonResponse
    {
        $user = $request->user();
        $institute = $user->institute;

        if (! $institute) {
            return $this->notFoundResponse('No institute associated.');
        }

        return $this->successResponse(new InstituteResource($institute));
    }

    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();
        $branches = $user->institute->branches()->get();

        return $this->successResponse(BranchResource::collection($branches));
    }
}
