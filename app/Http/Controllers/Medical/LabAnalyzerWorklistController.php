<?php

namespace App\Http\Controllers\Medical;

use App\Models\LabIntegration\LabAnalyzer;
use Illuminate\Http\Request;

class LabAnalyzerWorklistController extends MedicalController
{
    public function index(Request $request, LabAnalyzer $analyzer)
    {
        $this->ensureSameInstitute($analyzer);
        $query = $analyzer->worklists()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $worklists = $query->paginate(20)->withQueryString();

        return view('medical.lab.analyzers.worklist.index', compact('analyzer', 'worklists'));
    }
}
