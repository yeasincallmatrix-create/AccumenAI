<?php

namespace App\Livewire;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class StudentList extends DataTable
{
    protected const VIEW = 'livewire.students.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_students', [
            'serial', 'no', 'roll', 'name', 'phone', 'email', 'reg',
            'gender', 'dob', 'age', 'blood', 'religion', 'nationality',
            'nid', 'passport', 'branch', 'guardian', 'admission', 'status', 'action',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'no', 'roll', 'name', 'phone', 'email', 'reg',
            'gender', 'dob', 'age', 'blood', 'religion', 'nationality',
            'nid', 'passport', 'branch', 'guardian', 'admission', 'status', 'action',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
            'gender' => $request->query('gender', ''),
            'religion' => $request->query('religion', ''),
            'branch_id' => $request->query('branch_id', ''),
        ];
        $this->search = $request->query('q', '');
    }

    protected function baseQuery(): Builder
    {
        $instituteId = \App\Support\TenantContext::id()
            ?? auth()->user()?->institute_id
            ?? \App\Support\Workspace::id();

        $query = Student::query()->with('branch');
        if ($instituteId) {
            $query->where('students.institute_id', $instituteId);
        } else {
            // No tenant context -> return empty set to prevent cross-tenant leak (TenantScoped is no-op when disabled)
            $query->whereRaw('1=0');
        }

        return $query;
    }

    protected function searchableColumns(): array
    {
        return [
            'first_name', 'last_name', 'student_id_number',
            'phone', 'email', 'reg_no',
            'roll_number', 'guardian_phone', 'nationality',
        ];
    }

    protected function filterableColumns(): array
    {
        return [
            'status' => ['type' => 'exact'],
            'gender' => ['type' => 'exact'],
            'religion' => ['type' => 'exact'],
            'branch_id' => ['type' => 'exact', 'column' => 'branch_id'],
        ];
    }

    protected function sortableColumns(): array
    {
        return ['admission_date', 'dob', 'id'];
    }

    protected function defaultSort(): ?string
    {
        return null;
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        if ($key === 'branch_id') {
            $query->where('branch_id', (int) $value);
        } else {
            parent::applyFilter($query, $key, $value, $config);
        }
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search across multiple fields
        if (filled($this->search)) {
            $query->where(function (Builder $q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('student_id_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('reg_no', 'like', "%{$this->search}%")
                    ->orWhere('roll_number', 'like', "%{$this->search}%")
                    ->orWhere('guardian_phone', 'like', "%{$this->search}%");
            });
        }

        // Filters
        if (filled($this->filters['status'] ?? '')) {
            $query->where('status', $this->filters['status']);
        }
        if (filled($this->filters['gender'] ?? '')) {
            $query->where('gender', $this->filters['gender']);
        }
        if (filled($this->filters['religion'] ?? '')) {
            $query->where('religion', $this->filters['religion']);
        }
        if (filled($this->filters['branch_id'] ?? '')) {
            $query->where('branch_id', (int) $this->filters['branch_id']);
        }

        // Sort
        if ($this->sortField === 'admission_date' && $this->sortDirection) {
            $query->orderBy('admission_date', $this->sortDirection);
        } elseif ($this->sortField === 'dob' && $this->sortDirection) {
            $query->orderBy('dob', $this->sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            $query->latest('id');
        }

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_students', $this->visibleColumns);
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

    public function isColumnVisible(string $column): bool
    {
        return in_array($column, $this->visibleColumns, true);
    }

    public function render()
    {
        $user = auth()->user();
        $institute = $user?->institute;

        return view(self::VIEW, [
            'students' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'branches' => $institute ? \App\Models\Branch::where('institute_id', $institute->id)->orderBy('name')->get() : collect(),
        ]);
    }
}
