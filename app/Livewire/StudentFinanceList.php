<?php

namespace App\Livewire;

use App\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudentFinanceList extends DataTable
{
    protected const VIEW = 'livewire.education-finance.students.list';

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->perPage = 20;
    }

    protected function baseQuery(): Builder
    {
        return Student::query();
    }

    protected function searchableColumns(): array
    {
        return ['first_name', 'last_name', 'student_id_number'];
    }

    protected function sortableColumns(): array
    {
        return ['first_name', 'last_name', 'billed', 'collected', 'outstanding', 'overdue'];
    }

    protected function defaultSort(): ?string
    {
        return 'billed';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    public function getRows(): LengthAwarePaginator
    {
        $today = now()->toDateString();
        $instituteId = $this->resolveInstituteId();

        $query = Student::query()
            ->select(
                'students.id',
                'students.first_name',
                'students.last_name',
                'students.student_id_number'
            )
            ->selectRaw('COUNT(DISTINCT CASE WHEN i.status <> ? THEN i.id END) AS invoice_count', ['cancelled'])
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status <> ? THEN i.payable_amount ELSE 0 END), 0) AS billed', ['cancelled'])
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (?, ?) THEN i.due_amount ELSE 0 END), 0) AS outstanding', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (?, ?) AND i.due_date < ? THEN i.due_amount ELSE 0 END), 0) AS overdue', ['unpaid', 'partial', $today])
            ->selectRaw('COALESCE((
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                WHERE p.student_id = students.id
                    AND p.institute_id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM journals r
                        WHERE r.reversal_of = p.journal_id
                    )
            ), 0) AS collected', [$instituteId])
            ->leftJoin('invoices as i', function ($join) use ($instituteId) {
                $join->on('i.student_id', '=', 'students.id')
                    ->where('i.institute_id', '=', $instituteId);
            })
            ->groupBy('students.id', 'students.first_name', 'students.last_name', 'students.student_id_number');

        // Search
        if (filled($this->search)) {
            $like = '%'.$this->search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('students.first_name', 'like', $like)
                    ->orWhere('students.last_name', 'like', $like)
                    ->orWhere('students.student_id_number', 'like', $like);
            });
        }

        // Sort
        $sortField = $this->sortField ?? 'billed';
        $sortDirection = $this->sortDirection ?? 'desc';
        $allowedSorts = $this->sortableColumns();
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('billed', 'desc');
        }

        return $query->paginate($this->perPage)->withQueryString();
    }

    private function resolveInstituteId(): int
    {
        $user = auth()->user();
        if ($user instanceof \App\Models\InstituteUser) {
            return (int) $user->institute_id;
        }

        return (int) (\App\Support\TenantContext::id() ?? 0);
    }

    public function render()
    {
        return view(self::VIEW, [
            'students' => $this->getRows(),
        ]);
    }
}
