<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile v1 self-profile.
 *
 * Serves both populations: legacy per-institute accounts (institute_users) and
 * global accounts (users — owners/staff), whose institute + role come from the
 * membership row pinned on the token.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return ApiResponse::success([
                'user' => AuthController::userPayload($user),
                'institute' => $this->institutePayload($user->institute),
                'permissions' => $this->permissions($user),
            ]);
        }

        if ($user instanceof User) {
            $membership = $this->membership($user);

            if ($membership === null) {
                return ApiResponse::forbidden('No active institute workspace.');
            }

            return ApiResponse::success([
                'user' => AuthController::globalUserPayload($user, $membership),
                'institute' => $this->institutePayload($membership->institution),
                'permissions' => $this->globalPermissions($user, $membership),
            ]);
        }

        return ApiResponse::unauthorized();
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser && ! $user instanceof User) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'first_name' => 'sometimes|string|max:75',
            'last_name' => 'sometimes|string|max:75',
            'locale' => 'sometimes|in:bn,en',
            'preferred_language' => 'sometimes|in:bn,en',
        ]);

        if (array_key_exists('name', $validated)) {
            $parts = preg_split('/\s+/', trim($validated['name']), 2);
            $user->first_name = $parts[0] ?? $user->first_name;
            if (isset($parts[1])) {
                $user->last_name = $parts[1];
            }
        }

        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        $locale = $validated['locale'] ?? $validated['preferred_language'] ?? null;
        if ($locale !== null) {
            $user->preferred_language = $locale;
        }

        $user->save();

        if ($user instanceof User) {
            $membership = $this->membership($user);

            if ($membership === null) {
                return ApiResponse::forbidden('No active institute workspace.');
            }

            return ApiResponse::success([
                'user' => AuthController::globalUserPayload($user->fresh(), $membership),
            ]);
        }

        return ApiResponse::success([
            'user' => AuthController::userPayload($user->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function institutePayload(?Institute $institute): ?array
    {
        if ($institute === null) {
            return null;
        }

        $package = $institute->package;

        return [
            'id' => $institute->id,
            'name' => $institute->name,
            'slug' => $institute->slug,
            'industry' => $institute->industry,
            'logo_url' => $institute->logo_url,
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'tier' => $package->slug,
            ] : null,
        ];
    }

    private function membership(User $user): ?Membership
    {
        $token = $user->currentAccessToken();
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        return Workspace::activeMembershipFor($user, $abilities);
    }

    /**
     * @return list<string>
     */
    private function permissions(InstituteUser $user): array
    {
        if ($user->isOwner()) {
            return ['*'];
        }

        $role = $user->role;

        if ($role === null) {
            return [];
        }

        return $role->permissions->pluck('slug')->filter()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function globalPermissions(User $user, Membership $membership): array
    {
        if ($membership->isOwner() || $user->isOwnerAccount()) {
            return ['*'];
        }

        $role = $membership->role;

        if ($role === null) {
            return [];
        }

        return $role->permissions->pluck('slug')->filter()->values()->all();
    }
}
