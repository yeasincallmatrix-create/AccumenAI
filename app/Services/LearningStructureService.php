<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\StructureNode;
use App\Models\StructureTemplate;
use App\Models\StructureTemplateLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningStructureService
{
    public function __construct(private readonly LearningStructureResolver $resolver) {}

    public function getTemplateForInstitute(Institute $institute): ?StructureTemplate
    {
        return $this->resolver->resolveTemplate($institute)['template'];
    }

    public function getLevelsForTemplate(StructureTemplate $template): Collection
    {
        return $template->levels()->orderBy('level_order')->get();
    }

    public function getNodes(Institute $institute, ?int $branchId = null): Collection
    {
        $tpl = $this->getTemplateForInstitute($institute);
        if (! $tpl) return collect();
        $q = StructureNode::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('template_id', $tpl->id)
            ->where('status', true)
            ->orderBy('level_order')->orderBy('display_order')->orderBy('id');
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }
        return $q->get();
    }

    public function getNode(Institute $institute, int $nodeId, ?int $branchId = null): ?StructureNode
    {
        $q = StructureNode::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('id', $nodeId);
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }
        return $q->first();
    }

    /**
     * Assign template to institute (explicit override).
     */
    public function assignTemplateToInstitute(Institute $institute, StructureTemplate $template): InstituteSetting
    {
        if (! $template->status) {
            throw ValidationException::withMessages(['template' => 'Template is inactive.']);
        }
        if (! $template->is_global && (int) $template->institute_id !== (int) $institute->id) {
            throw ValidationException::withMessages(['template' => 'Cannot assign another institute private template.']);
        }

        return DB::transaction(function () use ($institute, $template) {
            $setting = InstituteSetting::withoutGlobalScope('institute')
                ->where('institute_id', $institute->id)->first();
            if (! $setting) {
                $setting = InstituteSetting::withoutGlobalScope('institute')->create([
                    'institute_id' => $institute->id,
                    'structure_template_id' => $template->id,
                ]);
            } else {
                $setting->forceFill(['structure_template_id' => $template->id])->save();
            }
            // audit placeholder
            // logger()->info('learning_structure.template_assigned', ['institute'=>$institute->id,'template'=>$template->id]);
            return $setting;
        });
    }

    public function createNode(Institute $institute, array $data, ?int $branchId = null): StructureNode
    {
        return DB::transaction(function () use ($institute, $data, $branchId) {
            $template = $this->resolveTemplateOrFail($institute, $data['template_id'] ?? null);
            $levelOrder = (int) ($data['level_order'] ?? 0);
            $templateLevel = StructureTemplateLevel::where('template_id', $template->id)->where('level_order', $levelOrder)->first();
            if (! $templateLevel) {
                throw ValidationException::withMessages(['level_order' => 'Invalid level for template.']);
            }
            if (isset($data['template_level_id']) && (int) $data['template_level_id'] !== (int) $templateLevel->id) {
                throw ValidationException::withMessages(['template_level_id' => 'Level does not belong to template.']);
            }
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            if (strlen($name) > 120) {
                throw ValidationException::withMessages(['name' => 'Name too long.']);
            }
            $parentId = $data['parent_node_id'] ?? null;
            if ($parentId !== null) {
                $this->validateParent($institute, $template, $templateLevel, (int) $parentId, $branchId);
            } else {
                if ($levelOrder !== 1) {
                    throw ValidationException::withMessages(['parent_node_id' => 'Parent is required for level > 1.']);
                }
            }
            $branchForNode = $branchId ?? ($data['branch_id'] ?? null);
            if ($branchForNode !== null) {
                $branch = Branch::withoutGlobalScope('institute')->where('id', $branchForNode)->where('institute_id', $institute->id)->first();
                if (! $branch) {
                    throw ValidationException::withMessages(['branch_id' => 'Invalid branch for institute.']);
                }
            }
            // Branch isolation: if child is branch-specific, parent must be shared or same branch
            if ($parentId !== null && $branchForNode !== null) {
                $parent = StructureNode::withoutGlobalScope('institute')->where('id', $parentId)->first();
                if ($parent && $parent->branch_id !== null && (int) $parent->branch_id !== (int) $branchForNode) {
                    throw ValidationException::withMessages(['branch_id' => 'Branch-specific child cannot attach to parent from another branch.']);
                }
            }

            $node = StructureNode::withoutGlobalScope('institute')->create([
                'institute_id' => $institute->id,
                'template_id' => $template->id,
                'template_level_id' => $templateLevel->id,
                'parent_node_id' => $parentId,
                'level_order' => $levelOrder,
                'name' => $name,
                'code' => isset($data['code']) ? trim($data['code']) : null,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'status' => (bool) ($data['status'] ?? true),
                'is_custom' => true,
                'branch_id' => $branchForNode,
                'metadata' => $data['metadata'] ?? null,
            ]);
            return $node->fresh();
        });
    }

    public function updateNode(Institute $institute, int $nodeId, array $data, ?int $branchId = null): StructureNode
    {
        return DB::transaction(function () use ($institute, $nodeId, $data, $branchId) {
            $node = StructureNode::withoutGlobalScope('institute')->where('institute_id', $institute->id)->where('id', $nodeId)->first();
            if (! $node) {
                throw ValidationException::withMessages(['node' => 'Node not found or not owned.']);
            }
            // Branch check for existing node
            if ($branchId !== null && $node->branch_id !== null && (int) $node->branch_id !== (int) $branchId && $node->branch_id !== null) {
                // If node is branch-specific and caller is different branch, deny unless caller is shared?
                // For simplicity, if branchId filter provided, disallow updating other branch nodes.
                throw ValidationException::withMessages(['node' => 'Cross-branch update not allowed.']);
            }
            $template = StructureTemplate::find($node->template_id);
            if (isset($data['name'])) {
                $name = trim($data['name']);
                if ($name === '' || strlen($name) > 120) {
                    throw ValidationException::withMessages(['name' => 'Invalid name.']);
                }
                $node->name = $name;
            }
            if (array_key_exists('code', $data)) {
                $node->code = $data['code'] !== null ? trim($data['code']) : null;
            }
            if (array_key_exists('display_order', $data)) {
                $node->display_order = (int) $data['display_order'];
            }
            if (array_key_exists('status', $data)) {
                $node->status = (bool) $data['status'];
            }
            if (array_key_exists('metadata', $data)) {
                $node->metadata = $data['metadata'];
            }
            // Parent move
            if (array_key_exists('parent_node_id', $data)) {
                $newParentId = $data['parent_node_id'] !== null ? (int) $data['parent_node_id'] : null;
                if ($newParentId !== $node->parent_node_id) {
                    if ($newParentId === $node->id) {
                        throw ValidationException::withMessages(['parent_node_id' => 'Node cannot be its own parent.']);
                    }
                    $level = StructureTemplateLevel::find($node->template_level_id);
                    if ($newParentId === null) {
                        if ($node->level_order !== 1) {
                            throw ValidationException::withMessages(['parent_node_id' => 'Only level 1 nodes can be root.']);
                        }
                    } else {
                        $this->validateParent($institute, $template, $level, $newParentId, $branchId);
                        $this->assertNoCycle($node->id, $newParentId);
                    }
                    $node->parent_node_id = $newParentId;
                }
            }
            // Branch move
            if (array_key_exists('branch_id', $data)) {
                $newBranch = $data['branch_id'] !== null ? (int) $data['branch_id'] : null;
                if ($newBranch !== null) {
                    $b = Branch::withoutGlobalScope('institute')->where('id', $newBranch)->where('institute_id', $institute->id)->first();
                    if (! $b) throw ValidationException::withMessages(['branch_id' => 'Invalid branch.']);
                    // Cross-branch parent check
                    if ($node->parent_node_id !== null) {
                        $parent = StructureNode::withoutGlobalScope('institute')->find($node->parent_node_id);
                        if ($parent && $parent->branch_id !== null && (int) $parent->branch_id !== (int) $newBranch) {
                            throw ValidationException::withMessages(['branch_id' => 'Branch-specific child cannot attach to parent from another branch.']);
                        }
                    }
                }
                $node->branch_id = $newBranch;
            }
            $node->save();
            return $node->fresh();
        });
    }

    public function deleteNode(Institute $institute, int $nodeId, ?int $branchId = null): void
    {
        DB::transaction(function () use ($institute, $nodeId, $branchId) {
            $node = StructureNode::withoutGlobalScope('institute')->where('institute_id', $institute->id)->where('id', $nodeId)->first();
            if (! $node) throw ValidationException::withMessages(['node' => 'Node not found.']);
            if ($branchId !== null && $node->branch_id !== null && (int) $node->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['node' => 'Cross-branch delete not allowed.']);
            }
            // Check children
            $hasChildren = StructureNode::withoutGlobalScope('institute')->where('parent_node_id', $node->id)->exists();
            if ($hasChildren) {
                throw ValidationException::withMessages(['node' => 'Cannot delete node with children.']);
            }
            // Check placement history
            $used = DB::table('student_placement_nodes')->where('node_id', $node->id)->exists();
            if ($used) {
                // Deactivate instead of hard delete, preserve history
                $node->forceFill(['status' => false])->save();
                return;
            }
            $node->delete();
        });
    }

    public function moveNode(Institute $institute, int $nodeId, ?int $newParentId, ?int $branchId = null): StructureNode
    {
        return $this->updateNode($institute, $nodeId, ['parent_node_id' => $newParentId], $branchId);
    }

    public function reorderNodes(Institute $institute, array $orderedIds, ?int $parentId = null, ?int $branchId = null): void
    {
        DB::transaction(function () use ($institute, $orderedIds, $parentId, $branchId) {
            if (empty($orderedIds)) return;
            $nodes = StructureNode::withoutGlobalScope('institute')->whereIn('id', $orderedIds)->where('institute_id', $institute->id)->get()->keyBy('id');
            if ($nodes->count() !== count($orderedIds)) {
                throw ValidationException::withMessages(['nodes' => 'One or more nodes not found or not owned.']);
            }
            // All must share same template, level, parent, branch scope
            $first = $nodes->get($orderedIds[0]);
            foreach ($orderedIds as $id) {
                $n = $nodes->get($id);
                if ((int) $n->template_id !== (int) $first->template_id || (int) $n->level_order !== (int) $first->level_order || (int) ($n->parent_node_id ?? 0) !== (int) ($parentId ?? 0)) {
                    throw ValidationException::withMessages(['nodes' => 'Reorder must be within same parent and level.']);
                }
                if ($branchId !== null && $n->branch_id !== null && (int) $n->branch_id !== (int) $branchId) {
                    throw ValidationException::withMessages(['nodes' => 'Cross-branch reorder not allowed.']);
                }
            }
            foreach ($orderedIds as $idx => $id) {
                $n = $nodes->get($id);
                $n->forceFill(['display_order' => $idx + 1])->save();
            }
        });
    }

    /**
     * Validate parent belongs to same institute/template and immediate preceding level.
     */
    public function validateParent(Institute $institute, StructureTemplate $template, StructureTemplateLevel $childLevel, int $parentId, ?int $branchId = null): void
    {
        $parent = StructureNode::withoutGlobalScope('institute')->where('id', $parentId)->where('institute_id', $institute->id)->first();
        if (! $parent) {
            throw ValidationException::withMessages(['parent_node_id' => 'Parent not found or not owned.']);
        }
        if ((int) $parent->template_id !== (int) $template->id) {
            throw ValidationException::withMessages(['parent_node_id' => 'Cross-template parent not allowed.']);
        }
        if ((int) $parent->level_order !== (int) $childLevel->level_order - 1) {
            throw ValidationException::withMessages(['parent_node_id' => 'Parent must be exactly one level above child.']);
        }
        if (! $parent->status) {
            throw ValidationException::withMessages(['parent_node_id' => 'Parent is inactive.']);
        }
        if ($branchId !== null) {
            if ($parent->branch_id !== null && (int) $parent->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['parent_node_id' => 'Cross-branch parent not allowed.']);
            }
        }
        // Also ensure parent's template_level matches its level_order
        $parentLevel = StructureTemplateLevel::find($parent->template_level_id);
        if ($parentLevel && (int) $parentLevel->level_order !== (int) $parent->level_order) {
            throw ValidationException::withMessages(['parent_node_id' => 'Parent level mismatch.']);
        }
    }

    private function assertNoCycle(int $nodeId, int $newParentId): void
    {
        $visited = [];
        $current = $newParentId;
        while ($current !== null) {
            if ($current === $nodeId) {
                throw ValidationException::withMessages(['parent_node_id' => 'Circular relationship detected.']);
            }
            if (isset($visited[$current])) break;
            $visited[$current] = true;
            $parent = StructureNode::withoutGlobalScope('institute')->select('parent_node_id')->where('id', $current)->first();
            $current = $parent?->parent_node_id;
            if (count($visited) > 50) break; // safety
        }
    }

    private function resolveTemplateOrFail(Institute $institute, ?int $templateId): StructureTemplate
    {
        if ($templateId !== null) {
            $tpl = StructureTemplate::find($templateId);
            if (! $tpl || ! $tpl->status) throw ValidationException::withMessages(['template_id' => 'Invalid template.']);
            if (! $tpl->is_global && (int) $tpl->institute_id !== (int) $institute->id) {
                throw ValidationException::withMessages(['template_id' => 'Cannot use another institute private template.']);
            }
            return $tpl;
        }
        $tpl = $this->getTemplateForInstitute($institute);
        if (! $tpl) throw ValidationException::withMessages(['template' => 'No template resolved for institute.']);
        return $tpl;
    }
}
