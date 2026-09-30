<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile v1 institutes visible to the token owner (usually 1).
 */
class InstituteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            $institute = $user->institute;

            return ApiResponse::success([
                'institutes' => $institute ? [[
                    'id' => $institute->id,
                    'name' => $institute->name,
                    'slug' => $institute->slug,
                    'industry' => $institute->industry,
                ]] : [],
            ]);
        }

        if ($user instanceof User) {
            $membership = Workspace::membershipFor($user);
            $institution = $membership?->institution;

            return ApiResponse::success([
                'institutes' => $institution ? [[
                    'id' => $institution->id,
                    'name' => $institution->name,
                    'slug' => $institution->slug,
                    'industry' => $institution->industry,
                ]] : [],
            ]);
        }

        return ApiResponse::unauthorized();
    }
}
