<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModuleAccessLog;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Universal Module Config — one matrix page where the platform admin decides,
 * per Industry × Sub-Industry, whether each registered module is:
 *
 *   mandatory — always on for matching tenants (Layer 3 base enable)
 *   default   — on unless the tenant explicitly disables it
 *   optional  — off until the tenant enables it
 *   hidden    — never surfaced to matching tenants (UI + toggle both blocked)
 *
 * Persisted into industry_subcategories + subcategory_default_modules, the
 * same tables read by ModuleAccessService::resolveSubCategoryModules().
 */
class UniversalModuleConfigController extends Controller
{
    private const CATEGORIES = ['mandatory', 'default', 'optional', 'hidden'];

    public function __construct(private readonly ModuleAccessService $moduleAccess) {}

    public function index(Request $request): View
    {
        $industries = $this->industryKeys();

        $industry = (string) $request->query('industry', '');
        if (! in_array($industry, $industries, true)) {
            $industry = $industries[0] ?? '';
        }

        $subcategoriesByIndustry = DB::table('industry_subcategories')
            ->where('is_active', true)
            ->orderBy('industry_key')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('industry_key');

        $subcategories = $subcategoriesByIndustry->get($industry, collect());

        $subcategoryKey = (string) $request->query('subcategory', '');
        $subcategory = $subcategories->firstWhere('subcategory_key', $subcategoryKey);

        $matrix = [];
        if ($subcategory) {
            $matrix = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $subcategory->id)
                ->pluck('category', 'module_key')
                ->all();
        }

        $subMap = $subcategoriesByIndustry
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'key' => $row->subcategory_key,
                'name' => $row->name,
            ])->values()->all())
            ->all();

        return view('admin.module-config.index', [
            'industries' => $industries,
            'industry' => $industry,
            'subcategories' => $subcategories,
            'subcategoriesByIndustry' => $subcategoriesByIndustry,
            'subMap' => $subMap,
            'subcategory' => $subcategory,
            'groups' => $this->loadModuleGroups(),
            'matrix' => $matrix,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'industry' => ['required', 'string', 'max:60', Rule::in($this->industryKeys())],
            'subcategory' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_key' => ['required', 'string', 'max:60', 'exists:module_registry,key'],
            'modules.*.category' => ['required', 'string', Rule::in(self::CATEGORIES)],
        ], [
            'subcategory.regex' => 'Sub-category key must be lowercase letters, numbers, underscores.',
        ]);

        $subcategory = DB::table('industry_subcategories')
            ->where('industry_key', $validated['industry'])
            ->where('subcategory_key', $validated['subcategory'])
            ->first();
        abort_if(! $subcategory, 404);

        // Last radio of a key wins — keeps a duplicate row idempotent.
        $assignments = [];
        foreach ($validated['modules'] as $row) {
            $assignments[$row['module_key']] = $row['category'];
        }

        $this->persistAssignments((int) $subcategory->id, $assignments);

        $this->flushCacheForSubcategory($validated['industry'], $validated['subcategory']);

        $this->logMatrixChange('matrix_update', $validated['industry'], $validated['subcategory'], $assignments, $request);

        $counts = array_count_values(array_values($assignments));

        return redirect()
            ->route('admin.module-config.index', [
                'industry' => $validated['industry'],
                'subcategory' => $validated['subcategory'],
            ])
            ->with('success', sprintf(
                'Configuration saved for %s / %s — mandatory %d, default %d, optional %d, hidden %d.',
                $validated['industry'],
                $validated['subcategory'],
                $counts['mandatory'] ?? 0,
                $counts['default'] ?? 0,
                $counts['optional'] ?? 0,
                $counts['hidden'] ?? 0,
            ));
    }

    public function copy(Request $request): RedirectResponse
    {
        $industryRule = ['required', 'string', 'max:60', Rule::in($this->industryKeys())];
        $keyRule = ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'];

        $validated = $request->validate([
            'source_industry' => $industryRule,
            'source_subcategory' => $keyRule,
            'target_industry' => $industryRule,
            'target_subcategory' => $keyRule,
        ], [
            'source_subcategory.regex' => 'Sub-category key must be lowercase letters, numbers, underscores.',
            'target_subcategory.regex' => 'Sub-category key must be lowercase letters, numbers, underscores.',
        ]);

        $source = DB::table('industry_subcategories')
            ->where('industry_key', $validated['source_industry'])
            ->where('subcategory_key', $validated['source_subcategory'])
            ->first();
        abort_if(! $source, 404);

        $target = DB::table('industry_subcategories')
            ->where('industry_key', $validated['target_industry'])
            ->where('subcategory_key', $validated['target_subcategory'])
            ->first();
        abort_if(! $target, 404);

        $redirect = redirect()->route('admin.module-config.index', [
            'industry' => $target->industry_key,
            'subcategory' => $target->subcategory_key,
        ]);

        if ($source->id === $target->id) {
            return $redirect->with('error', 'Source and target sub-category are the same.');
        }

        $assignments = DB::transaction(function () use ($source, $target) {
            $rows = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $source->id)
                ->get();

            DB::table('subcategory_default_modules')
                ->where('subcategory_id', $target->id)
                ->delete();

            $now = now();
            foreach ($rows as $row) {
                DB::table('subcategory_default_modules')->insert([
                    'subcategory_id' => $target->id,
                    'module_key' => $row->module_key,
                    'category' => $row->category,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $rows->pluck('category', 'module_key')->all();
        });

        $this->flushCacheForSubcategory($target->industry_key, $target->subcategory_key);

        $this->logMatrixChange('matrix_copy', $target->industry_key, $target->subcategory_key, $assignments, $request, "{$source->industry_key}/{$source->subcategory_key}");

        return $redirect->with('success', sprintf(
            'Copied %d module setting(s) from %s / %s to %s / %s.',
            count($assignments),
            $source->industry_key,
            $source->subcategory_key,
            $target->industry_key,
            $target->subcategory_key,
        ));
    }

    /**
     * Idempotent write: a single upsert keyed on (subcategory_id, module_key)
     * refreshes existing categories and inserts new keys, in one statement per
     * save. Re-saving is always safe — no duplicate rows, no lost created_at.
     */
    private function persistAssignments(int $subcategoryId, array $assignments): void
    {
        $now = now();

        $rows = [];
        foreach ($assignments as $moduleKey => $category) {
            $rows[] = [
                'subcategory_id' => $subcategoryId,
                'module_key' => $moduleKey,
                'category' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            DB::table('subcategory_default_modules')->upsert(
                $rows,
                ['subcategory_id', 'module_key'],
                ['category', 'updated_at'],
            );
        });
    }

    /**
     * Layer 3 feeds getEnabledModules() which is memoised per institute, so
     * every tenant matching this sub-category must have its cache dropped or
     * they keep serving the pre-save module set for up to an hour.
     */
    private function flushCacheForSubcategory(string $industry, string $subcategory): void
    {
        DB::table('institutes')
            ->where('industry', $industry)
            ->where('subcategory_key', $subcategory)
            ->pluck('id')
            ->each(function ($id): void {
                $this->moduleAccess->flushCache((int) $id);
                $this->moduleAccess->flushFeatureCache((int) $id);
            });
    }

    private function logMatrixChange(
        string $action,
        string $industry,
        string $subcategory,
        array $assignments,
        Request $request,
        ?string $source = null,
    ): void {
        $counts = array_count_values(array_values($assignments));

        ModuleAccessLog::create([
            'institute_id' => null,
            'module_key' => sprintf('*config*%s.%s', $industry, $subcategory),
            'action' => $action,
            'risk_level' => 'medium',
            'actor_id' => $request->user()?->id,
            'actor_type' => 'platform_admin',
            'previous_state' => null,
            'new_state' => sprintf('modules:%d', count($assignments)),
            'notes' => json_encode([
                'industry' => $industry,
                'subcategory' => $subcategory,
                'source' => $source,
                'counts' => $counts,
            ]),
            'reason' => $source ? 'Sub-category matrix copy' : 'Sub-category matrix update',
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Groups come from config/module_groups.php; each group's keys are resolved
     * against module_registry so unregistered (forward-looking) keys never
     * render as broken rows. Groups with no live module still render — the
     * empty state tells the admin the category exists but is unseeded.
     *
     * Elaboration: a module that owns active children (module_registry.parent_key)
     * is elaborated once as a parent row carrying one indented, collapsible row
     * per child; childless modules stay a single row. Child keys listed on their
     * own in config are claimed by their parent first, so a module can never
     * render twice. The registry only holds depth-2 hierarchies (parent →
     * parent.child), so direct children are enough to elaborate a group.
     */
    private function loadModuleGroups(): array
    {
        $registryRows = DB::table('module_registry')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();

        $registry = $registryRows->keyBy('key');

        $childrenByParent = $registryRows
            ->filter(fn ($module) => $module->parent_key !== null)
            ->groupBy('parent_key');

        $groups = [];
        foreach (config('module_groups', []) as $key => $group) {
            $configured = [];
            foreach ($group['modules'] ?? [] as $moduleKey) {
                if ($registry->has($moduleKey)) {
                    $configured[] = $moduleKey;
                }
            }

            // Pass 1 — claim every configured key that will render under its parent.
            $claimed = [];
            foreach ($configured as $moduleKey) {
                foreach ($childrenByParent->get($moduleKey, collect()) as $child) {
                    $claimed[$child->key] = true;
                }
            }

            // Pass 2 — parents (with their children) plus childless singles.
            $modules = [];
            foreach ($configured as $moduleKey) {
                if (isset($claimed[$moduleKey])) {
                    continue;
                }

                $module = $registry->get($moduleKey);
                $children = $childrenByParent->get($moduleKey, collect());

                $modules[] = [
                    'key' => $module->key,
                    'name' => $module->name,
                    'icon' => $module->icon,
                    'parent_key' => $module->parent_key,
                    'has_children' => $children->isNotEmpty(),
                    'children' => $children->map(fn ($child) => [
                        'key' => $child->key,
                        'name' => $child->name,
                        'icon' => $child->icon,
                    ])->values()->all(),
                ];
            }

            $groups[$key] = [
                'label' => $group['label'] ?? $key,
                'icon' => $group['icon'] ?? 'bi-puzzle',
                'description' => $group['description'] ?? '',
                'modules' => $modules,
                'child_count' => array_sum(array_map(fn ($module) => count($module['children']), $modules)),
            ];
        }

        return $groups;
    }

    /**
     * Valid industry keys come from config('industry-modules') minus the 'core'
     * pseudo-entry — same source the other taxonomy screens use.
     *
     * @return array<int, string>
     */
    private function industryKeys(): array
    {
        return array_values(array_diff(array_keys(config('industry-modules', [])), ['core']));
    }
}
