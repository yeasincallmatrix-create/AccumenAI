<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentAdminController extends Controller
{
    public const STUDENTS_COLUMNS = [
        'serial', 'student_id', 'name', 'institute', 'gender', 'phone',
        'email', 'admission', 'status', 'action',
    ];

    public function index(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Student::query()
            ->with('institute')
            ->when($request->query('q'), fn ($query, $term) => $query->search($term))
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('type'), function ($query, $type) {
                $query->whereHas('enrollments.course.category', fn ($query) => $query->where('subject_type', $type));
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status));

        $items = (clone $query)->latest('id')->paginate(20)->withQueryString();

        $allItems = (clone $query)->latest('id')->get();

        $visibleColumns = $request->user()->preference('students_columns', self::STUDENTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::STUDENTS_COLUMNS, (array) $visibleColumns));

        return view('admin.students.index', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'type' => $request->query('type'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::STUDENTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('students_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function show(Student $student): View
    {
        $student->load(['institute', 'branch', 'enrollments.batch.course']);

        return view('admin.students.show', ['student' => $student]);
    }
}
