<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\GeoDuplicatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 4: duplicate detection and safe resolution for geography data.
 *
 * Same-name-different-parent rows (Kaliganj in four districts) are
 * legitimate and never listed. Everything here is country-scoped and
 * destructive actions run in transactions with clear 422 messages.
 */
class GeoDuplicatesController extends Controller
{
    public function index(Request $request): View
    {
        $countryId = (int) $request->query('country_id', 0);
        $country = $countryId > 0
            ? Country::query()->find($countryId)
            : Country::query()->where('iso2', 'BD')->first() ?? Country::query()->orderBy('name')->first();

        $scan = $country ? app(GeoDuplicatesService::class)->scan($country) : null;

        return view('admin.geo.duplicates', [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso2']),
            'country' => $country,
            'scan' => $scan,
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['integer'],
            'keep_id' => ['nullable', 'integer'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);

        try {
            $result = app(GeoDuplicatesService::class)->merge($country, $data['ids'], $data['keep_id'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Merge failed: '.$e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "Merged {$result['merged']} row(s) into #{$result['survivor']}: {$result['children_moved']} children and {$result['refs_moved']} reference(s) moved.", 'data' => $result]);
    }

    public function destroy(Request $request, int $unit): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);

        try {
            $result = app(GeoDuplicatesService::class)->destroyRow($country, $unit);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Row not found in this country.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Delete failed: '.$e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Row deleted.', 'data' => $result]);
    }
}
