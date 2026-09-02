<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\InstituteUser;
use App\Models\Training\Enrollment;
use App\Models\TeacherAcademicAssignment;
use Illuminate\Database\Eloquent\Builder;

class BatchList extends DataTable
{
    protected const VIEW = 'livewire.batches.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_batches', [
            'serial', 'code', 'name', 'course', 'shift', 'start', 'seats', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'code', 'name', 'course', 'shift', 'start', 'seats', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'course_id' => $request->query('course_id', ''),
            'branch_id' => $request->query('branch_id', ''),
            'academic_year_id' => $request->query('academic_year_id', ''),
            'instructor_id' => $request->query('instructor_id', ''),
            'status' => $request->query('status', ''),
        ];
        $this->search = $request->query('q', '');
    }

    protected function baseQuery(): Builder
    {
        return Batch::query()
            ->with(['course:id,name', 'course.subjects:id,name', 'academicYear:id,name,code'])
            ->withCount(['exams as attended_exams' => fn ($q) => $q->whereHas('results')]);
    }

    protected function searchableColumns(): array
    {
        return ['name', 'batch_code'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'name', 'start_date'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'course_id' => $query->where('course_id', (int) $value),
            'branch_id' => $query->where('branch_id', (int) $value),
            'academic_year_id' => $query->where('academic_year_id', (int) $value),
            'instructor_id' => $query->whereIn('id', TeacherAcademicAssignment::query()
                ->where('institute_user_id', (int) $value)
                ->where('status', 'active')
                ->whereNotNull('batch_id')
                ->pluck('batch_id')),
            'status' => $query->where('status', $value),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search
        if (filled($this->search)) {
            $query->where(function (Builder $q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('batch_code', 'like', "%{$this->search}%");
            });
        }

        // Filters
        foreach ($this->filters as $key => $value) {
            if (filled($value)) {
                match ($key) {
                    'course_id' => $query->where('course_id', (int) $value),
                    'branch_id' => $query->where('branch_id', (int) $value),
                    'academic_year_id' => $query->where('academic_year_id', (int) $value),
                    'instructor_id' => $query->whereIn('id', TeacherAcademicAssignment::query()
                        ->where('institute_user_id', (int) $value)
                        ->where('status', 'active')
                        ->whereNotNull('batch_id')
                        ->pluck('batch_id')),
                    'status' => $query->where('status', $value),
                };
            }
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_batches', $this->visibleColumns);
        }
    }

    public function toggleColumn(string $column): void
    {
        $index = array_search($column, $this->visibleColumns, true);
        if ($index !== false) {
            unset($this->visibleColumns[$index]);
            $this->visibleColumns = array_values($this->visibleColumns);
        } else {
            $this->visibleColumns[] = $column;
        }
        $this->saveColumns();
    }

    public function render()
    {
        $user = auth()->user();
        $institute = $user?->institute;
        $instituteId = $institute?->id;

        $courses = $instituteId
            ? \App\Models\InstituteCourse::where('institute_id', $instituteId)
                ->with('course:id,name')
                ->get()
                ->pluck('course.name', 'course.id')
            : collect();

        $academicYears = $instituteId
            ? \App\Models\AcademicYear::where('institute_id', $instituteId)->orderBy('name')->get()
            : collect();

        $instructors = $instituteId
            ? InstituteUser::where('institute_id', $instituteId)
                ->whereHas('academicAssignments', fn ($q) => $q->where('status', 'active')->whereNotNull('batch_id'))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
            : collect();

        $branches = $instituteId
            ? \App\Models\Branch::where('institute_id', $instituteId)->orderBy('name')->get()
            : collect();

        return view(self::VIEW, [
            'batches' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'courses' => $courses,
            'academicYears' => $academicYears,
            'instructors' => $instructors,
            'branches' => $branches,
        ]);
    }
}
