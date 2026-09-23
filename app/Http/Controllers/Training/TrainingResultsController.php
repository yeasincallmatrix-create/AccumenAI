<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrainingResultsController extends Controller
{
    public function index(Request $request)
    {
        return view('training.attendance.index');
    }

    public function store(Request $request)
    {
        return redirect()->back()->with('success', 'Attendance recorded.');
    }

    public function bulkStore(Request $request)
    {
        return redirect()->back()->with('success', 'Bulk attendance recorded.');
    }
}
