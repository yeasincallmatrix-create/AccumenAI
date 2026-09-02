<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultsController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = Batch::query()
            ->where('institute_id', $instituteId)
            ->with(['course:id,name', 'exams.results', 'enrollments'])
            ->withCount(['enrollments', 'exams'])
            ->orderBy('name')
            ->get()
            ->map(function (Batch $batch) use ($instituteId) {
                $total = $batch->enrollments->count();
                $passed = 0;
                foreach ($batch->exams as $exam) {
                    $passed += $exam->results->where('status', 'pass')->count();
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

    public function publish(Request $request, Batch $batch)
    {
        // Delegate to TrainingResultController for single responsibility
        return app(\App\Http\Controllers\Training\TrainingResultController::class)->publish($request, $batch);
    }

    public function downloadMarksheet(Request $request, Batch $batch, $trainee)
    {
        return app(\App\Http\Controllers\Training\TrainingResultController::class)->downloadMarksheet($request, $batch, $trainee);
    }
}
