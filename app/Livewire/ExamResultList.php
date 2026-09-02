<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Result;
use Illuminate\Database\Eloquent\Builder;

class ExamResultList extends DataTable
{
    protected const VIEW = 'livewire.exams.results';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_exam_results', [
            'serial', 'student', 'student_id', 'batch', 'total_marks', 'obtained', 'percentage', 'grade', 'status', 'published_at',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'student', 'student_id', 'batch', 'total_marks', 'obtained', 'percentage', 'grade', 'status', 'published_at',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'result_batch_id' => $request->query('result_batch_id', ''),
            'result_status' => $request->query('result_status', ''),
        ];
        $this->search = $request->query('rq', '');

        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        return Result::query()
            ->with(['student:id,first_name,last_name,student_id_number', 'batch:id,name,batch_code', 'course:id,name'])
            ->withCount('certificate');
    }

    protected function searchableColumns(): array
    {
        return ['student.first_name', 'student.last_name', 'student.student_id_number'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'total_marks', 'obtained_marks', 'percentage', 'published_at'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'result_batch_id' => $query->where('batch_id', (int) $value),
            'result_status' => $query->where('result_status', $value),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (filled($this->search)) {
            $query->whereHas('student', function (Builder $w) {
                $w->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('student_id_number', 'like', "%{$this->search}%");
            });
        }

        if (filled($this->filters['result_batch_id'] ?? '')) {
            $query->where('batch_id', (int) $this->filters['result_batch_id']);
        }
        if (filled($this->filters['result_status'] ?? '')) {
            $query->where('result_status', $this->filters['result_status']);
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_exam_results', $this->visibleColumns);
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
            ? Batch::where('institute_id', $instituteId)->orderBy('name')->get(['id', 'name', 'batch_code'])
            : collect();

        return view(self::VIEW, [
            'results' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'batches' => $batches,
            'resultStatusBadge' => [
                'pass' => 'bg-success',
                'fail' => 'bg-danger',
                'pending' => 'bg-secondary',
            ],
            'resultStatusNames' => [
                'pass' => 'Pass',
                'fail' => 'Fail',
                'pending' => 'Unpublished',
            ],
        ]);
    }
}
