<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\StructureTemplate;
use App\Models\User;
use App\Services\LearningStructureResolver;
use App\Services\LearningStructureService;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningStructureSettingsController extends Controller
{
    public function __construct(private readonly LearningStructureResolver $resolver, private readonly LearningStructureService $service) {}

    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();
        if ($user instanceof InstituteUser) return Institute::find($user->institute_id);
        if ($user instanceof User) {
            $m = Workspace::membership();
            if ($m) {
                $inst = Institute::find($m->institution_id);
                if ($inst) return $inst;
            }
            $wid = Workspace::id() ?? \App\Support\TenantContext::id();
            if ($wid) {
                $inst = Institute::find($wid);
                if ($inst) return $inst;
            }
            $first = \App\Models\Membership::where('user_id', $user->id)->where('status','active')->first();
            if ($first) return Institute::find($first->institution_id);
        }
        return null;
    }

    public function index(Request $request): View
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;
        // Validate branch belongs
        if ($branchId) {
            $ok = \App\Models\Branch::withoutGlobalScope('institute')->where('id',$branchId)->where('institute_id',$institute->id)->exists();
            if (!$ok) abort(403);
        } else {
            $m = Workspace::membership();
            if ($m && $m->branch_id) $branchId = (int)$m->branch_id;
        }

        $resolved = $this->resolver->resolveTemplate($institute);
        $template = $resolved['template'];
        $source = $resolved['source'];
        $isCustomized = $source === 'explicit';
        $structure = $this->resolver->resolve($institute, $branchId);
        $branches = \App\Models\Branch::withoutGlobalScope('institute')->where('institute_id',$institute->id)->get();
        $templates = StructureTemplate::where('is_global', true)->where('status', true)->orderBy('name')->get();

        return view('institute.learning-structure-settings', [
            'institute' => $institute,
            'template' => $template,
            'source' => $source,
            'isCustomized' => $isCustomized,
            'structure' => $structure,
            'branches' => $branches,
            'templates' => $templates,
            'branchId' => $branchId,
        ]);
    }

    public function assignTemplate(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $data = $request->validate(['template_id' => ['required','integer','exists:structure_templates,id']]);
        $template = StructureTemplate::findOrFail($data['template_id']);
        $this->service->assignTemplateToInstitute($institute, $template);
        return redirect()->route('academic.structure.settings')->with('status', 'Template assigned: '.$template->name);
    }

    public function storeNode(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $data = $request->validate([
            'level_order' => ['required','integer','min:1','max:10'],
            'parent_node_id' => ['nullable','integer'],
            'name' => ['required','string','max:120'],
            'branch_id' => ['nullable','integer'],
        ]);
        $m = Workspace::membership();
        $branchFilter = $m && $m->branch_id ? (int)$m->branch_id : null;
        // If request branch_id provided and user is not branch-restricted, use it; else use filter
        $effectiveBranch = $branchFilter ?? ($data['branch_id'] ?? null);
        if ($effectiveBranch !== null) $data['branch_id'] = $effectiveBranch;
        $this->service->createNode($institute, $data, $branchFilter);
        return redirect()->route('academic.structure.settings', ['branch_id' => $branchFilter])->with('status', 'Node created');
    }

    public function updateNode(Request $request, int $node): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $data = $request->validate([
            'name' => ['nullable','string','max:120'],
            'status' => ['nullable','boolean'],
        ]);
        $m = Workspace::membership();
        $branchFilter = $m && $m->branch_id ? (int)$m->branch_id : null;
        $this->service->updateNode($institute, $node, $data, $branchFilter);
        return redirect()->route('academic.structure.settings', ['branch_id' => $branchFilter])->with('status', 'Node updated');
    }

    public function destroyNode(Request $request, int $node): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $m = Workspace::membership();
        $branchFilter = $m && $m->branch_id ? (int)$m->branch_id : null;
        $this->service->deleteNode($institute, $node, $branchFilter);
        return redirect()->route('academic.structure.settings', ['branch_id' => $branchFilter])->with('status', 'Node removed');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if(!$institute, 404);
        $data = $request->validate([
            'ordered_ids' => ['required','array'],
            'ordered_ids.*' => ['integer'],
            'parent_node_id' => ['nullable','integer'],
        ]);
        $m = Workspace::membership();
        $branchFilter = $m && $m->branch_id ? (int)$m->branch_id : null;
        $this->service->reorderNodes($institute, $data['ordered_ids'], $data['parent_node_id'] ?? null, $branchFilter);
        return redirect()->route('academic.structure.settings', ['branch_id' => $branchFilter])->with('status', 'Order updated');
    }
}
