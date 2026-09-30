<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\InstituteUser;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile v1 offline sync.
 *
 * delta: read-only changed-record feed for an explicit entity allowlist
 * (tenant-scoped via TenantContext, cursor = updated/created timestamp).
 * push: idempotent upserts keyed by client-supplied natural keys,
 * echoing client_id per operation.
 */
class SyncController extends Controller
{
    private const DELTA_ENTITIES = ['branches', 'notifications'];

    public function delta(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'since' => 'sometimes|date',
            'entities' => 'sometimes|string',
        ]);

        $since = isset($validated['since'])
            ? \Carbon\Carbon::parse($validated['since'])
                // Storage holds app-timezone-naive datetimes; align the
                // cursor to the same convention so lexical comparison is exact.
                ->setTimezone(date_default_timezone_get())
            : now()->subDays(7);

        $requested = isset($validated['entities'])
            ? array_filter(array_map('trim', explode(',', $validated['entities'])))
            : self::DELTA_ENTITIES;

        $unknown = array_diff($requested, self::DELTA_ENTITIES);
        if ($unknown !== []) {
            return ApiResponse::validation(['entities' => ['Unknown entity: '.implode(', ', $unknown)]]);
        }

        $instituteId = TenantContext::id() ?? $user->institute_id;
        $entities = [];

        if (in_array('branches', $requested, true)) {
            $entities['branches'] = Branch::where('institute_id', $instituteId)
                ->where('updated_at', '>', $since)
                ->orderBy('updated_at')
                ->get(['id', 'code', 'name', 'phone', 'email', 'address', 'status', 'updated_at']);
        }

        if (in_array('notifications', $requested, true)) {
            $entities['notifications'] = DB::table('notifications')
                ->where(function ($q) use ($instituteId, $user) {
                    $q->where(function ($q) use ($instituteId) {
                        $q->where('scope', 'institute')
                            ->where('institute_id', $instituteId);
                    })->orWhere(function ($q) use ($user) {
                        $q->where('scope', 'user')
                            ->where('target_user_type', 'institute_user')
                            ->where('target_user_id', $user->id);
                    });
                })
                ->where('created_at', '>', $since)
                ->orderBy('created_at')
                ->get(['id', 'category', 'title', 'message', 'link_url', 'created_at']);
        }

        return ApiResponse::success(
            ['entities' => $entities],
            ['next_since' => now()->toISOString()]
        );
    }

    public function push(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'operations' => 'required|array|max:100',
            'operations.*.entity' => 'required|string',
            'operations.*.action' => 'required|in:create,update,upsert',
            'operations.*.client_id' => 'required|string|max:80',
            'operations.*.payload' => 'required|array',
        ]);

        $instituteId = TenantContext::id() ?? $user->institute_id;
        $results = [];

        foreach ($validated['operations'] as $op) {
            $results[] = match ($op['entity']) {
                'branches' => $this->pushBranch($instituteId, $op),
                default => [
                    'client_id' => $op['client_id'],
                    'status' => 'error',
                    'error' => 'Unknown entity: '.$op['entity'],
                ],
            };
        }

        return ApiResponse::success(['results' => $results]);
    }

    /**
     * @param array{action: string, client_id: string, payload: array} $op
     * @return array{client_id: string, status: string, server_id?: int, error?: string}
     */
    private function pushBranch(int $instituteId, array $op): array
    {
        $payload = $op['payload'];

        if (empty($payload['code']) || empty($payload['name'])) {
            return [
                'client_id' => $op['client_id'],
                'status' => 'error',
                'error' => 'Branch payload requires code and name.',
            ];
        }

        $branch = Branch::updateOrCreate(
            ['institute_id' => $instituteId, 'code' => $payload['code']],
            array_intersect_key($payload, array_flip([
                'name', 'phone', 'email', 'address', 'status',
            ]))
        );

        return [
            'client_id' => $op['client_id'],
            'status' => 'ok',
            'server_id' => $branch->id,
        ];
    }
}
