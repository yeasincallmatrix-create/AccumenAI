<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrainingFeesController extends Controller
{
    public function index(Request $request)
    {
        return view('training.fees.index');
    }
}

class TrainingReportsController extends Controller
{
    public function index(Request $request)
    {
        return view('training.reports.index');
    }
}

class TrainingSettingController extends Controller
{
    public function index(Request $request)
    {
        return view('training.settings.index');
    }

    public function update(Request $request)
    {
        return redirect()->back()->with('success', 'Settings updated.');
    }
}

class TrainingMarksController extends Controller
{
    public function index(Request $request)
    {
        return view('training.marks.index');
    }

    public function store(Request $request)
    {
        return redirect()->back()->with('success', 'Marks recorded.');
    }
}
