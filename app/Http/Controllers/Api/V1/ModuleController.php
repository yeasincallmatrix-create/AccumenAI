<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Institute;
use App\Models\InstituteUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile v1 module registry.
 *
 * index($instituteId): active modules filtered by the institute's
 * (package_id × industry) matrix in package_industry_modules, mirroring
 * ModuleAccessService semantics (category wins; null category = enabled
 * flag; no rows = legacy = all active).
 */
class ModuleController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof InstituteUser) {
            return ApiResponse::unauthorized();
        }

        if ((int) $user->institute_id !== $id) {
            return ApiResponse::forbidden('Institute out of scope for this token.');
        }

        $institute = Institute::find($id);

        if (! $institute) {
            return ApiResponse::notFound('Institute not found.');
        }

        $allowed = $this->allowedKeys($institute);
        $modules = DB::table('module_registry')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->when($allowed !== null, fn ($c) => $c->whereIn('key', $allowed))
            ->values()
            ->map(fn ($m) => [
                'key' => $m->key,
                'name' => $m->name,
                'icon' => $m->icon,
                'parent_key' => $m->parent_key,
                'type' => $m->type,
                'sort_order' => (int) $m->sort_order,
            ]);

        return ApiResponse::success(['modules' => $modules]);
    }

    public function all(): JsonResponse
    {
        $modules = DB::table('module_registry')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => [
                'key' => $m->key,
                'name' => $m->name,
                'icon' => $m->icon,
                'parent_key' => $m->parent_key,
                'type' => $m->type,
                'sort_order' => (int) $m->sort_order,
            ]);

        return ApiResponse::success(['modules' => $modules]);
    }

    /**
     * @return list<string>|null null = no matrix rows → legacy (all active)
     */
    private function allowedKeys(Institute $institute): ?array
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return null;
        }

        if (empty($institute->package_id) || empty($institute->industry)) {
            return null;
        }

        $rows = DB::table('package_industry_modules')
            ->where('package_id', $institute->package_id)
            ->where('industry_key', $institute->industry)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $allowed = [];
        foreach ($rows as $row) {
            $category = $row->category ?? null;
            if ($category !== null) {
                if (in_array($category, ['mandatory', 'default'], true)) {
                    $allowed[] = $row->module_key;
                }
                continue;
            }
            if ((bool) $row->enabled) {
                $allowed[] = $row->module_key;
            }
        }

        return $allowed;
    }
}
