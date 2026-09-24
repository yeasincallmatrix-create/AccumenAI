<?php

namespace App\Livewire\Training;

use App\Livewire\DataTable;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingExam;
use App\Models\Training\TrainingExamResult;
use Illuminate\Database\Eloquent\Builder;

class ExamList extends DataTable
{
    protected const VIEW = 'livewire.training.exams.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_training_exams', [
            'serial', 'title', 'course', 'batch', 'subjects', 'date', 'marks', 'students', 'pass', 'fail', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'title', 'course', 'batch', 'subjects', 'date', 'marks', 'students', 'pass', 'fail', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'batch_id' => $request->query('batch_id', ''),
            'status' => $request->query('status', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        // TrainingExam::subjects is an ACCESSOR (course subjects) — never eager-load it.
        return TrainingExam::query()
            ->with(['batch:id,name,batch_code', 'course:id,name'])
            ->withCount('results');
    }

    protected function searchableColumns(): array
    {
        return ['title'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'title', 'created_at'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'batch_id' => $query->where('batch_id', (int) $value),
            'status' => $query->where('status', $value),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (filled($this->search)) {
            $query->where('title', 'like', "%{$this->search}%");
        }

        if (filled($this->filters['batch_id'] ?? '')) {
            $query->where('batch_id', (int) $this->filters['batch_id']);
        }
        if (filled($this->filters['status'] ?? '')) {
            $query->where('status', $this->filters['status']);
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_training_exams', $this->visibleColumns);
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

        $batches = $instituteId
            ? TrainingBatch::where('institute_id', $instituteId)->orderBy('name')->get(['id', 'name', 'batch_code'])
            : collect();

        $exams = $this->getRows();

        // Compute pass/fail counts from TrainingExamResult (no exam_subjects pivot in training)
        $this->attachStudentCentricCounts($exams);

        return view(self::VIEW, [
            'exams' => $exams,
            'user' => $user,
            'institute' => $institute,
            'batches' => $batches,
            'statusNames' => [
                'scheduled' => 'Scheduled',
                'ongoing' => 'Ongoing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'statusBadge' => [
                'scheduled' => 'bg-secondary',
                'ongoing' => 'bg-info',
                'completed' => 'bg-success',
                'cancelled' => 'bg-danger',
            ],
        ]);
    }

    /**
     * Attach pass_count / fail_count (result rows) to each exam in the paginator.
     * Training has no exam_subjects pivot — counts come from TrainingExamResult.
     */
    private function attachStudentCentricCounts($exams): void
    {
        foreach ($exams as $exam) {
            $exam->pass_count = TrainingExamResult::where('exam_id', $exam->id)
                ->where('result_status', 'pass')
                ->count();
            $exam->fail_count = TrainingExamResult::where('exam_id', $exam->id)
                ->where('result_status', 'fail')
                ->count();
            $exam->students_count = TrainingExamResult::where('exam_id', $exam->id)
                ->distinct()
                ->count('student_id');
        }
    }
}
