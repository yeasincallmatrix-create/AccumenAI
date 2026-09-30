<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InstituteUser;
use App\Models\MobileDevice;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile v1 device (FCM token) registry.
 */
class DeviceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'fcm_token' => 'required|string|max:255',
            'platform' => 'required|in:android,ios',
            'device_name' => 'required|string|max:120',
            'app_version' => 'sometimes|nullable|string|max:20',
        ]);

        $instituteId = TenantContext::id() ?? $user->institute_id;

        $device = MobileDevice::updateOrCreate(
            ['institute_id' => $instituteId, 'fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'],
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return ApiResponse::success(['device' => [
            'id' => $device->id,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
        ]], null, 201);
    }

    public function unregister(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $instituteId = TenantContext::id() ?? $user->institute_id;

        $device = MobileDevice::where('institute_id', $instituteId)
            ->where('user_id', $user->id)
            ->where('fcm_token', $token)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('Device not found.');
        }

        $device->forceFill(['revoked_at' => now()])->save();

        return ApiResponse::success(null);
    }
}
