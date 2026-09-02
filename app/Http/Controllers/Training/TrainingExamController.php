<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingExamController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $exams = Exam::where('institute_id', $instituteId)
            ->with(['batch.course'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $batches = Batch::where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'running'])
            ->orderBy('name')
            ->get();

        return view('training.exams.index', compact('exams', 'batches'));
    }
}
