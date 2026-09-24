<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultsController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = TrainingBatch::query()
            ->where('institute_id', $instituteId)
            ->with(['course:id,name', 'exams.results', 'enrollments'])
            ->withCount(['enrollments', 'exams'])
            ->orderBy('name')
            ->get()
            ->map(function (TrainingBatch $batch) use ($instituteId) {
                $total = $batch->enrollments->count();
                $passed = 0;
                foreach ($batch->exams as $exam) {
                    $passed += $exam->results->where('result_status', 'pass')->count();
                }
                $batch->setAttribute('computed_total', $total);
                $batch->setAttribute('computed_passed', $passed);
                $batch->setAttribute('computed_rate', $total > 0 ? round($passed / $total * 100, 1) : 0);
                // Published status from training_batch_results
                $publishedCount = \App\Models\TrainingBatchResult::where('batch_id', $batch->id)->where('institute_id', $instituteId)->whereNotNull('published_at')->count();
                $batch->setAttribute('published_count', $publishedCount);
                $batch->setAttribute('is_published', $publishedCount > 0);
                return $batch;
            });

        return view('training.results.index', compact('batches'));
    }
}
