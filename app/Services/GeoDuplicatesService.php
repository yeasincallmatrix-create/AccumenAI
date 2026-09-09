<?php

namespace App\Services;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4: duplicate detection and safe resolution for geography data.
 *
 * Two dup shapes are scanned per country:
 *  - name groups: same level + name + parent (true duplicates — e.g. Dhaka×2);
 *  - code groups: same code with different names (identity-key collision).
 *
 * Same names under different parents (Kaliganj in four districts) are
 * legitimate and never reported.
 */
class GeoDuplicatesService
{
    public const GROUP_LIMIT = 100;

    public const MEMBER_LIMIT = 20;

    /** Reference columns pointing at administrative units. */
    public const REF_COLUMNS = [
        'patients.present_admin_1_id',
        'patients.present_admin_2_id',
        'patients.present_admin_3_id',
        'institutes.admin_level_1_id',
        'institutes.admin_level_2_id',
        'institutes.admin_level_3_id',
    ];

    /**
     * Scan a country for duplicate groups.
     *
     * @return array{name_groups: array, name_group_count: int, code_groups: array, code_group_count: int}
     */
    public function scan(Country $country): array
    {
        $nameGroups = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->selectRaw('administrative_level_id, name, parent_id, COUNT(*) c, GROUP_CONCAT(id) ids')
            ->groupBy('administrative_level_id', 'name', 'parent_id')
            ->having('c', '>', 1)
            ->orderByDesc('c')
            ->limit(self::GROUP_LIMIT)
            ->get();

        $codeGroups = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->whereNotNull('code')
            ->selectRaw('code, COUNT(*) c, GROUP_CONCAT(id) ids, GROUP_CONCAT(DISTINCT name SEPARATOR \' | \') names')
            ->groupBy('code')
            ->having('c', '>', 1)
            ->orderByDesc('c')
            ->limit(self::GROUP_LIMIT)
            ->get()
            ->filter(fn ($g) => count(array_unique(explode(' | ', (string) $g->names))) > 1);

        return [
            'name_groups' => $nameGroups->map(fn ($g) => $this->describeGroup($country, explode(',', (string) $g->ids)))->all(),
            'name_group_count' => $nameGroups->count(),
            'code_groups' => $codeGroups->values()->map(fn ($g) => $this->describeGroup($country, explode(',', (string) $g->ids)))->all(),
            'code_group_count' => $codeGroups->count(),
        ];
    }

    /**
     * Merge duplicate rows: repoint children + address references to the
     * survivor, delete the losers. Members must share country, level and
     * name (case-insensitive); the survivor keeps its own parent.
     *
     * @return array{survivor: int, merged: int, children_moved: int, refs_moved: int}
     */
    public function merge(Country $country, array $ids, ?int $keepId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ids' => ['Select at least two rows to merge.'],
            ]);
        }

        $rows = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->whereIn('id', $ids)
            ->get();
        if ($rows->count() !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ids' => ['Some rows do not belong to this country.'],
            ]);
        }
        if ($rows->pluck('administrative_level_id')->unique()->count() > 1
            || $rows->map(fn ($r) => mb_strtolower(trim((string) $r->name)))->unique()->count() > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ids' => ['Only rows with the same level and name can be merged.'],
            ]);
        }

        $survivor = $keepId !== null
            ? $rows->firstWhere('id', $keepId)
            : $rows->sortBy('id')->first();
        if ($survivor === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'keep_id' => ['Chosen survivor is not in the group.'],
            ]);
        }

        $losers = $rows->where('id', '!==', $survivor->id)->pluck('id')->all();
        $result = ['survivor' => (int) $survivor->id, 'merged' => 0, 'children_moved' => 0, 'refs_moved' => 0];

        DB::transaction(function () use ($losers, $survivor, &$result) {
            $result['children_moved'] = AdministrativeUnit::query()
                ->whereIn('parent_id', $losers)
                ->update(['parent_id' => $survivor->id]);

            foreach (self::REF_COLUMNS as $col) {
                [$table, $column] = explode('.', $col);
                $result['refs_moved'] += DB::table($table)->whereIn($column, $losers)->update([$column => $survivor->id]);
            }

            $result['merged'] = AdministrativeUnit::query()->whereIn('id', $losers)->delete();
        });

        return $result;
    }

    /**
     * Delete one row, but only when nothing points at it.
     *
     * @return array{deleted: int}
     */
    public function destroyRow(Country $country, int $id): array
    {
        $row = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->whereKey($id)
            ->firstOrFail();

        $children = AdministrativeUnit::query()->where('parent_id', $id)->count();
        if ($children > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ["Cannot delete '{$row->name}': {$children} child row(s) still point at it. Merge or move them first."],
            ]);
        }

        foreach (self::REF_COLUMNS as $col) {
            [$table, $column] = explode('.', $col);
            $refs = DB::table($table)->where($column, $id)->count();
            if ($refs > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id' => ["Cannot delete '{$row->name}': {$refs} row(s) in {$table} still reference it."],
                ]);
            }
        }

        $row->delete();

        return ['deleted' => 1];
    }

    /** Member detail for one group of ids. */
    private function describeGroup(Country $country, array $ids): array
    {
        $rows = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->whereIn('id', array_slice(array_map('intval', $ids), 0, self::MEMBER_LIMIT))
            ->with('parent:id,name')
            ->orderBy('id')
            ->get();

        return [
            'members' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'code' => $r->code,
                'level_id' => $r->administrative_level_id,
                'parent' => $r->parent?->name,
                'children' => AdministrativeUnit::query()->where('parent_id', $r->id)->count(),
                'refs' => $this->refCount($r->id),
            ])->all(),
        ];
    }

    private function refCount(int $id): int
    {
        $total = 0;
        foreach (self::REF_COLUMNS as $col) {
            [$table, $column] = explode('.', $col);
            $total += DB::table($table)->where($column, $id)->count();
        }

        return $total;
    }
}
