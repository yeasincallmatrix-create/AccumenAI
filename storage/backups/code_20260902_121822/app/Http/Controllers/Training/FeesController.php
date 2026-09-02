<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeesController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = Batch::query()
            ->where('institute_id', $instituteId)
            ->with(['course:id,name,fee', 'enrollments'])
            ->withCount('enrollments')
            ->orderBy('name')
            ->get();

        // Reuse finance fee logic filtered for training_center — show batch-wise fee summary
        return view('training.fees.index', compact('batches'));
    }
}
