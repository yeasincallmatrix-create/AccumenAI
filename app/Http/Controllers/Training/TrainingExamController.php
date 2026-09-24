<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingExam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingExamController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $exams = TrainingExam::where('institute_id', $instituteId)
            ->with(['batch.course'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $batches = TrainingBatch::where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'running'])
            ->orderBy('name')
            ->get();

        return view('training.exams.index', compact('exams', 'batches'));
    }

    public function show($id): View
    {
        $exam = TrainingExam::with(['results'])->findOrFail($id);
        $exam->setRelation('batch', $exam->batch_id ? TrainingBatch::find($exam->batch_id) : null);
        return view('training.exams.show', compact('exam'));
    }
}
