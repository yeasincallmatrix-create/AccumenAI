<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\IndustryTemplateMapping;
use App\Models\Institute;
use App\Models\StructureNode;
use App\Models\StructureTemplate;
use Illuminate\Support\Collection;

/**
 * Generic Learning Structure Engine resolver.
 *
 * Priority for template resolution:
 *  1. Explicit institute_settings.structure_template_id
 *  2. Country + industry + sub_industry mapping (status=true, ordered by priority then id)
 *  3. Industry + sub_industry mapping (country_id NULL)
 *  4. Industry mapping (sub_industry NULL, country NULL or matched)
 *  5. Global education fallback (industry=education, sub_industry NULL)
 *  6. Safe fallback to School template (code=school, is_global, status true)
 */
class LearningStructureResolver
{
    /**
     * Resolve the effective template for an institute.
     * @return array{template: ?StructureTemplate, source: string}  source = explicit|mapping|fallback|null
     */
    public function resolveTemplate(Institute $institute): array
    {
        // 1. Explicit assignment
        $settings = $institute->settings()->first();
        if ($settings && filled($settings->structure_template_id)) {
            $tpl = StructureTemplate::find($settings->structure_template_id);
            if ($tpl && $tpl->status) {
                // ownership check: global or same institute
                if ($tpl->is_global || (int) $tpl->institute_id === (int) $institute->id) {
                    return ['template' => $tpl, 'source' => 'explicit'];
                }
            }
        }

        $countryId = $institute->country_id;
        $industry = $institute->industry;
        $sub = $institute->sub_industry;

        // Build ordered candidates: most specific first
        $candidates = collect();

        if ($countryId && $industry && $sub) {
            $candidates->push(['industry' => $industry, 'sub_industry' => $sub, 'country_id' => $countryId]);
        }
        if ($industry && $sub) {
            $candidates->push(['industry' => $industry, 'sub_industry' => $sub, 'country_id' => null]);
        }
        if ($countryId && $industry) {
            $candidates->push(['industry' => $industry, 'sub_industry' => null, 'country_id' => $countryId]);
        }
        if ($industry) {
            $candidates->push(['industry' => $industry, 'sub_industry' => null, 'country_id' => null]);
        }
        // Global education fallback
        $candidates->push(['industry' => 'education', 'sub_industry' => null, 'country_id' => null]);

        foreach ($candidates as $c) {
            $map = $this->findMapping($c['industry'], $c['sub_industry'], $c['country_id']);
            if ($map) {
                $tpl = StructureTemplate::find($map->structure_template_id);
                if ($tpl && $tpl->status) {
                    return ['template' => $tpl, 'source' => 'mapping'];
                }
            }
        }

        // 6. Safe fallback: School
        $fallback = StructureTemplate::where('code', 'school')->where('is_global', true)->where('status', true)->first();
        if ($fallback) {
            return ['template' => $fallback, 'source' => 'fallback'];
        }

        return ['template' => null, 'source' => 'none'];
    }

    /**
     * Resolve template without writing to DB (default vs custom distinguishable).
     */
    public function resolveTemplateFor(Institute $institute, ?int $branchId = null): ?StructureTemplate
    {
        return $this->resolveTemplate($institute)['template'];
    }

    /**
     * Resolve full structure for institute (N-level generic).
     * Returns: ['template'=>StructureTemplate, 'levels'=>[...], 'source'=>string]
     * Each level: ['level_order','label','label_key','value_source','required','has_values','nodes'=>[...tree...]]
     * Nodes are filtered by branch scope and ordered, children nested.
     */
    public function resolve(Institute $institute, ?int $branchId = null): array
    {
        $resolved = $this->resolveTemplate($institute);
        $template = $resolved['template'];
        if (! $template) {
            return ['template' => null, 'levels' => [], 'source' => 'none', 'branch_id' => $branchId];
        }

        $levels = $template->levels()->orderBy('level_order')->get();
        // Single query for all nodes of this template+institute, filtered by branch
        $nodesQuery = StructureNode::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('template_id', $template->id)
            ->where('status', true)
            ->orderBy('level_order')
            ->orderBy('display_order')
            ->orderBy('id');

        // Branch scoping: shared (null) + requested branch
        if ($branchId !== null) {
            $nodesQuery->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }
        // If branchId is null, return only shared nodes? Actually for admin view return all shared + branch-agnostic?
        // Spec: shared visible to all, branch-specific only to that branch.
        // When branchId is null (no branch context), show only shared nodes.
        if ($branchId === null) {
            // Show all? But to avoid leaking branch nodes to generic context, show shared + all branch nodes? Spec says branch-specific only to that branch.
            // For generic resolve (no branch filter), we should show shared nodes only to avoid cross-branch leakage.
            // However for institute-level admin, they should see all. We therefore when branchId is null, do NOT filter by branch — but we must document.
            // Decision: when branchId is null, return shared + all (no branch filter) is admin view. We achieve by not applying branch filter at all when branchId === null should mean no restriction.
            // Rebuild query without branch filter in that case.
            $nodesQuery = StructureNode::withoutGlobalScope('institute')
                ->where('institute_id', $institute->id)
                ->where('template_id', $template->id)
                ->where('status', true)
                ->orderBy('level_order')
                ->orderBy('display_order')
                ->orderBy('id');
        }

        $allNodes = $nodesQuery->get();

        // Build tree in memory
        $nodesByParent = $allNodes->groupBy(fn ($n) => $n->parent_node_id ?? 'root');
        $nodesById = $allNodes->keyBy('id');

        $buildTree = function ($parentId) use (&$buildTree, $nodesByParent) {
            $children = $nodesByParent->get($parentId ?? 'root', collect())->values();
            return $children->map(function ($node) use (&$buildTree) {
                $arr = $node->toArray();
                $arr['children'] = $buildTree($node->id);
                return $arr;
            })->all();
        };

        $levelsArray = $levels->map(function ($lvl) use ($nodesByParent, $buildTree, $allNodes) {
            // For each level, collect root nodes at that level? Actually nodes are tree, root is level 1.
            // We provide level-scoped nodes: all nodes at this level_order, with children nested.
            // For level 1, children are those with parent null.
            // For deeper levels, we don't duplicate — we provide tree already via level 1.
            // To keep both views, we provide 'nodes' as top-level tree when level_order==1, else flat list.
            if ($lvl->level_order === 1) {
                $tree = $buildTree(null);
                // Filter tree to only nodes at level 1 (already)
                $filtered = collect($tree)->filter(fn ($n) => (int) $n['level_order'] === 1)->values()->all();
                return [
                    'level_order' => $lvl->level_order,
                    'label' => $lvl->label,
                    'label_key' => $lvl->label_key,
                    'value_source' => $lvl->value_source,
                    'required' => (bool) $lvl->required,
                    'has_values' => (bool) $lvl->has_values,
                    'nodes' => $filtered,
                ];
            }
            // For non-root levels, provide flat list (children already nested under level 1)
            $flat = $allNodes->where('level_order', $lvl->level_order)->values()->map(fn ($n) => $n->toArray())->all();
            return [
                'level_order' => $lvl->level_order,
                'label' => $lvl->label,
                'label_key' => $lvl->label_key,
                'value_source' => $lvl->value_source,
                'required' => (bool) $lvl->required,
                'has_values' => (bool) $lvl->has_values,
                'nodes' => $flat,
            ];
        })->all();

        return [
            'template' => $template,
            'levels' => $levelsArray,
            'source' => $resolved['source'],
            'branch_id' => $branchId,
        ];
    }

    /**
     * Get visible nodes for a specific level and optional parent filter.
     */
    public function getNodesForLevel(Institute $institute, int $levelOrder, ?int $parentNodeId = null, ?int $branchId = null): Collection
    {
        $resolved = $this->resolveTemplate($institute);
        $template = $resolved['template'];
        if (! $template) {
            return collect();
        }
        $q = StructureNode::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('template_id', $template->id)
            ->where('level_order', $levelOrder)
            ->where('status', true)
            ->orderBy('display_order')->orderBy('id');

        if ($parentNodeId !== null) {
            $q->where('parent_node_id', $parentNodeId);
        } else {
            // For level 1, parent is null; for deeper levels without parent filter, return all at that level
            if ($levelOrder === 1) {
                $q->whereNull('parent_node_id');
            }
        }

        if ($branchId !== null) {
            $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $q->get();
    }

    private function findMapping(string $industry, ?string $subIndustry, ?int $countryId): ?IndustryTemplateMapping
    {
        $q = IndustryTemplateMapping::where('industry', $industry)
            ->where('status', true)
            ->orderBy('priority')->orderBy('id');

        if ($subIndustry === null) {
            $q->whereNull('sub_industry');
        } else {
            $q->where('sub_industry', $subIndustry);
        }

        if ($countryId === null) {
            $q->whereNull('country_id');
        } else {
            $q->where('country_id', $countryId);
        }

        return $q->first();
    }
}
