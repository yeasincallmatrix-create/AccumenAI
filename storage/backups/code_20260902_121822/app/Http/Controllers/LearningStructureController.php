<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\StructureNode;
use App\Models\User;
use App\Services\LearningStructureResolver;
use App\Services\LearningStructureService;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearningStructureController extends Controller
{
    public function __construct(
        private readonly LearningStructureResolver $resolver,
        private readonly LearningStructureService $service
    ) {}

    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();
        if ($user instanceof InstituteUser) {
            return Institute::find($user->institute_id);
        }
        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership) {
                $inst = Institute::find($membership->institution_id);
                if ($inst) return $inst;
                $inst = Institute::withoutGlobalScopes()->find($membership->institution_id);
                if ($inst) return $inst;
            }
            $wid = Workspace::id() ?? \App\Support\TenantContext::id();
            if ($wid !== null) {
                $direct = \App\Models\Membership::where('user_id', $user->id)->where('institution_id', $wid)->where('status', 'active')->first();
                if ($direct) {
                    $inst = Institute::find($direct->institution_id);
                    if ($inst) return $inst;
                }
            }
            $first = \App\Models\Membership::where('user_id', $user->id)->where('status', 'active')->orderBy('institution_id')->first();
            if ($first) {
                $inst = Institute::find($first->institution_id);
                if ($inst) return $inst;
            }
            return null;
        }
        return null;
    }

    private function resolveBranchId(Request $request, Institute $institute): ?int
    {
        // Branch comes from authenticated membership or query param if authorized
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');
        if ($branchId !== null) {
            $branchId = (int) $branchId;
            $branch = \App\Models\Branch::withoutGlobalScope('institute')->where('id', $branchId)->where('institute_id', $institute->id)->first();
            if (! $branch) {
                abort(403, 'Invalid branch for institute.');
            }
            // If user is branch-restricted, enforce membership branch
            $membership = Workspace::membership();
            if ($membership && $membership->branch_id !== null && (int) $membership->branch_id !== $branchId) {
                abort(403, 'Branch access denied.');
            }
            return $branchId;
        }
        // Default to membership branch if exists
        $membership = Workspace::membership();
        if ($membership && $membership->branch_id !== null) {
            return (int) $membership->branch_id;
        }
        return null;
    }

    public function options(Request $request): JsonResponse
    {
        if ($request->has('institute_id') || $request->has('tenant_id')) {
            return response()->json(['success' => false, 'message' => 'Invalid parameter'], 422);
        }
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        $data = $this->resolver->resolve($institute, $branchId);
        $template = $data['template'];
        return response()->json([
            'success' => true,
            'data' => [
                'template' => $template ? ['id' => $template->id, 'code' => $template->code, 'name' => $template->name] : null,
                'source' => $data['source'],
                'branch_id' => $data['branch_id'],
                'levels' => array_map(fn ($l) => [
                    'level_order' => $l['level_order'],
                    'label_key' => $l['label_key'],
                    'label' => $l['label'],
                    'value_source' => $l['value_source'],
                    'required' => $l['required'],
                    'has_values' => $l['has_values'],
                    'nodes' => $l['level_order'] === 1 ? ($l['nodes'] ?? []) : [],
                ], $data['levels']),
            ],
        ]);
    }

    public function nodes(Request $request): JsonResponse
    {
        if ($request->has('institute_id') || $request->has('tenant_id')) {
            return response()->json(['success' => false, 'message' => 'Invalid parameter'], 422);
        }
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);

        $data = $request->validate([
            'level_order' => ['required', 'integer', 'min:1', 'max:10'],
            'parent_node_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $levelOrder = (int) $data['level_order'];
        $parentId = $data['parent_node_id'] ?? null;
        if ($parentId !== null) $parentId = (int) $parentId;

        // Use branch from query if explicitly passed and validated, else membership branch
        $effectiveBranch = $branchId;
        if (isset($data['branch_id']) && $data['branch_id'] !== null) {
            $effectiveBranch = $this->resolveBranchId(new Request(['branch_id' => $data['branch_id']]), $institute);
        }

        // Validate template/level
        $template = $this->service->getTemplateForInstitute($institute);
        if (! $template) return response()->json(['success' => false, 'message' => 'No template'], 422);
        $level = \App\Models\StructureTemplateLevel::where('template_id', $template->id)->where('level_order', $levelOrder)->first();
        if (! $level) return response()->json(['success' => false, 'message' => 'Invalid level'], 422);

        // If parent provided, validate ownership and level adjacency
        if ($parentId !== null) {
            $parent = \App\Models\StructureNode::withoutGlobalScope('institute')->where('id', $parentId)->where('institute_id', $institute->id)->first();
            if (! $parent) return response()->json(['success' => false, 'message' => 'Parent not found'], 422);
            if ((int) $parent->template_id !== (int) $template->id) return response()->json(['success' => false, 'message' => 'Cross-template parent'], 422);
            if ((int) $parent->level_order !== $levelOrder - 1) return response()->json(['success' => false, 'message' => 'Invalid parent level'], 422);
            if ($effectiveBranch !== null && $parent->branch_id !== null && (int) $parent->branch_id !== (int) $effectiveBranch) {
                return response()->json(['success' => false, 'message' => 'Cross-branch parent'], 403);
            }
        } else {
            if ($levelOrder !== 1) return response()->json(['success' => false, 'message' => 'Parent required for level > 1'], 422);
        }

        $nodes = $this->resolver->getNodesForLevel($institute, $levelOrder, $parentId, $effectiveBranch);
        return response()->json([
            'success' => true,
            'data' => [
                'label' => $level->label,
                'label_key' => $level->label_key,
                'level_order' => $levelOrder,
                'options' => $nodes->map(fn ($n) => [
                    'id' => $n->id,
                    'parent_node_id' => $n->parent_node_id,
                    'level_order' => $n->level_order,
                    'name' => $n->name,
                    'code' => $n->code,
                    'branch_id' => $n->branch_id,
                    'status' => (bool) $n->status,
                ])->values()->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('institute_id') || $request->has('tenant_id')) {
            return response()->json(['success' => false, 'message' => 'Invalid parameter'], 422);
        }
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        $data = $request->validate([
            'level_order' => ['required', 'integer', 'min:1', 'max:10'],
            'parent_node_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer', Rule::exists('structure_templates', 'id')],
        ]);
        try {
            $node = $this->service->createNode($institute, $data, $branchId);
            return response()->json(['success' => true, 'data' => $node], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, int $node): JsonResponse
    {
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'parent_node_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);
        try {
            $updated = $this->service->updateNode($institute, $node, $data, $branchId);
            return response()->json(['success' => true, 'data' => $updated]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    public function destroy(Request $request, int $node): JsonResponse
    {
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        try {
            $this->service->deleteNode($institute, $node, $branchId);
            return response()->json(['success' => true]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    public function move(Request $request, int $node): JsonResponse
    {
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        $data = $request->validate(['parent_node_id' => ['nullable', 'integer']]);
        try {
            $moved = $this->service->moveNode($institute, $node, $data['parent_node_id'] ?? null, $branchId);
            return response()->json(['success' => true, 'data' => $moved]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }

    public function reorder(Request $request): JsonResponse
    {
        $institute = $this->resolveInstitute($request);
        if (! $institute) return response()->json(['success' => false, 'message' => 'Institute not found'], 404);
        $branchId = $this->resolveBranchId($request, $institute);
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
            'parent_node_id' => ['nullable', 'integer'],
        ]);
        try {
            $this->service->reorderNodes($institute, $data['ordered_ids'], $data['parent_node_id'] ?? null, $branchId);
            return response()->json(['success' => true]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }
}
