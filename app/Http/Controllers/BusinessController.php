<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $institute = Institute::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Only active/verified institutes are publicly visible
        // Pending/suspended still show but with notice
        $branches = $institute->branches()->whereNull('deleted_at')->where('status', 'active')->get();
        $coursesCount = $institute->instituteCourses()->count();
        $studentsCount = $institute->students()->count();
        $batchesCount = $institute->batches()->where('status', '!=', 'cancelled')->count();

        // Recent courses (via instituteCourses)
        $courses = $institute->instituteCourses()
            ->with('course')
            ->latest()
            ->limit(6)
            ->get();

        return view('business.show', [
            'institute' => $institute,
            'branches' => $branches,
            'coursesCount' => $coursesCount,
            'studentsCount' => $studentsCount,
            'batchesCount' => $batchesCount,
            'courses' => $courses,
            'backUrl' => url()->previous() !== url()->current() ? url()->previous() : route('dashboard'),
        ]);
    }
}
