<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\LabAnalyzerRequest;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\Medical\ClinicalAuditLog;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Services\LabIntegration\DeviceAuthService;
use Illuminate\Http\Request;

class LabAnalyzerController extends MedicalController
{
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = LabAnalyzer::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('manufacturer', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%");
            });
        }
        if ($request->filled('type')) {
            $query->where('instrument_type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $analyzers = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('medical.lab.analyzers.index', compact('analyzers'));
    }

    public function create(AnalyzerAdapterRegistry $registry)
    {
        $adapterKeys = array_keys($registry->all());

        return view('medical.lab.analyzers.create', compact('adapterKeys'));
    }

    public function store(LabAnalyzerRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();
        $data['branch_id'] = $this->branchContextId();
        $data['adapter_version'] = $data['adapter_version'] ?? 'v1';
        $data['status'] = 'inactive';

        $analyzer = LabAnalyzer::create($data);
        $this->audit($analyzer, 'analyzer.created', ['new' => ClinicalAuditLog::snapshot($analyzer)]);

        return redirect()
            ->route('medical.laboratory.analyzers.show', $analyzer)
            ->with('success', "Analyzer '{$analyzer->name}' created. Issue a device credential to start receiving results.");
    }

    public function show(LabAnalyzer $analyzer)
    {
        $this->ensureSameInstitute($analyzer);
        $analyzer->load(['parameterMaps', 'credential']);
        $recentMessages = $analyzer->messages()->orderByDesc('id')->limit(10)->get();
        $stats = [
            'total_messages' => $analyzer->messages()->count(),
            'failed_messages' => $analyzer->messages()->whereIn('status', ['error', 'dead'])->count(),
            'pending_messages' => $analyzer->messages()->whereIn('status', ['received', 'parsed'])->count(),
        ];

        return view('medical.lab.analyzers.show', compact('analyzer', 'recentMessages', 'stats'));
    }

    public function edit(LabAnalyzer $analyzer, AnalyzerAdapterRegistry $registry)
    {
        $this->ensureSameInstitute($analyzer);
        $adapterKeys = array_keys($registry->all());

        return view('medical.lab.analyzers.edit', compact('analyzer', 'adapterKeys'));
    }

    public function update(LabAnalyzerRequest $request, LabAnalyzer $analyzer)
    {
        $this->ensureSameInstitute($analyzer);
        $old = ClinicalAuditLog::snapshot($analyzer);
        $analyzer->update($request->validated());
        [$oldChanged, $newChanged] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($analyzer->fresh()));
        $this->audit($analyzer, 'analyzer.updated', ['old' => $oldChanged, 'new' => $newChanged]);

        return redirect()
            ->route('medical.laboratory.analyzers.show', $analyzer)
            ->with('success', 'Analyzer updated.');
    }

    public function destroy(LabAnalyzer $analyzer)
    {
        $this->ensureSameInstitute($analyzer);
        $old = ClinicalAuditLog::snapshot($analyzer);
        $analyzer->delete();
        $this->audit($analyzer, 'analyzer.deleted', ['old' => $old]);

        return redirect()
            ->route('medical.laboratory.analyzers.index')
            ->with('success', 'Analyzer deleted.');
    }

    public function issueCredential(LabAnalyzer $analyzer, DeviceAuthService $auth)
    {
        $this->ensureSameInstitute($analyzer);
        if ($analyzer->credential) {
            return back()->with('warning', 'Credential already exists. Rotate instead.');
        }
        $token = $auth->issue($analyzer);
        $this->audit($analyzer, 'credential.issued');

        return redirect()
            ->route('medical.laboratory.analyzers.show', $analyzer)
            ->with('success', 'Credential issued. Copy the token now — it is shown once only.')
            ->with('plain_token', $token);
    }

    public function rotateCredential(LabAnalyzer $analyzer, DeviceAuthService $auth)
    {
        $this->ensureSameInstitute($analyzer);
        $token = $auth->rotate($analyzer);
        $this->audit($analyzer, 'credential.rotated');

        return redirect()
            ->route('medical.laboratory.analyzers.show', $analyzer)
            ->with('success', 'Credential rotated. Update your gateway .env.')
            ->with('plain_token', $token);
    }

    public function revokeCredential(LabAnalyzer $analyzer, DeviceAuthService $auth)
    {
        $this->ensureSameInstitute($analyzer);
        $auth->revoke($analyzer);
        $this->audit($analyzer, 'credential.revoked');

        return back()->with('success', 'Credential revoked.');
    }

    /**
     * Best-effort audit — UI actions must never fail because logging did.
     */
    protected function audit(LabAnalyzer $analyzer, string $action, array $options = []): void
    {
        try {
            ClinicalAuditLog::record($analyzer, $action, $options);
        } catch (\Throwable $e) {
            \Log::warning('LabAnalyzer audit failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
