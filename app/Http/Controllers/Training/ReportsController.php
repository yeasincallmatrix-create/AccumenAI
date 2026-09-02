<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = Batch::query()
            ->where('institute_id', $instituteId)
            ->withCount(['enrollments', 'exams', 'certificates'])
            ->get();

        $totalEnrollments = $batches->sum('enrollments_count');
        $totalBatches = $batches->count();
        $totalCertificates = Certificate::where('institute_id', $instituteId)->count();
        $completionRate = $totalEnrollments > 0 ? round($totalCertificates / $totalEnrollments * 100, 1) : 0;

        return view('training.reports.index', compact('batches', 'totalEnrollments', 'totalBatches', 'totalCertificates', 'completionRate'));
    }
}
