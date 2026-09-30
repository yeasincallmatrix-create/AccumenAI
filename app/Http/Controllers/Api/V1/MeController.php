<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InstituteUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile v1 self-profile.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $institute = $user->institute;
        $package = $institute?->package;

        return ApiResponse::success([
            'user' => AuthController::userPayload($user),
            'institute' => $institute ? [
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
            ] : null,
            'permissions' => $this->permissions($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
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

        return ApiResponse::success([
            'user' => AuthController::userPayload($user->fresh()),
        ]);
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
}
