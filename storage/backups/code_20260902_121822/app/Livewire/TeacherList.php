<?php

namespace App\Livewire;

use App\Models\InstituteUser;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Builder;

class TeacherList extends DataTable
{
    protected const VIEW = 'livewire.teachers.list';

    public function mount(): void
    {
        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
            'branch_id' => $request->query('branch_id', ''),
            'designation' => $request->query('designation', ''),
            'employment_status' => $request->query('employment_status', ''),
            'qualification' => $request->query('qualification', ''),
        ];
        $this->search = $request->query('q', '');
    }

    protected function baseQuery(): Builder
    {
        $teacherRoleId = \App\Models\Role::where('name', 'Teacher')->first()?->id;
        $instituteId = \App\Support\TenantContext::id()
            ?? auth()->user()?->institute_id
            ?? \App\Support\Workspace::id();

        $query = InstituteUser::query()
            ->where('role_id', $teacherRoleId)
            ->with(['branch', 'teacherProfile']);

        if ($instituteId) {
            $query->where('institute_users.institute_id', $instituteId);
        } else {
            $query->whereRaw('1=0');
        }

        return $query;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'first_name', 'last_name', 'employee_id', 'email', 'phone'];
    }

    protected function sortableColumns(): array
    {
        return ['name', 'first_name', 'status', 'id'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'status' => $query->where('status', $value),
            'branch_id' => $query->where('branch_id', (int) $value),
            'designation' => $query->where('designation', 'like', "%{$value}%"),
            'employment_status' => $query->whereHas('teacherProfile', fn ($q) => $q->where('employment_status', $value)),
            'qualification' => $query->where('qualification', 'like', "%{$value}%"),
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
                    ->orWhere('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('employee_id', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        // Filters
        foreach ($this->filters as $key => $value) {
            if (filled($value)) {
                match ($key) {
                    'status' => $query->where('status', $value),
                    'branch_id' => $query->where('branch_id', (int) $value),
                    'designation' => $query->where('designation', 'like', "%{$value}%"),
                    'employment_status' => $query->whereHas('teacherProfile', fn ($q) => $q->where('employment_status', $value)),
                    'qualification' => $query->where('qualification', 'like', "%{$value}%"),
                };
            }
        }

        $query->orderBy('name');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function render()
    {
        $user = auth()->user();
        $institute = $user?->institute;
        $instituteId = $institute?->id;

        $branches = $instituteId
            ? \App\Models\Branch::where('institute_id', $instituteId)->orderBy('name')->get()
            : collect();

        $designations = $instituteId
            ? InstituteUser::where('institute_id', $instituteId)
                ->whereNotNull('designation')
                ->distinct()
                ->pluck('designation')
                ->filter()
                ->values()
            : collect();

        $qualifications = $instituteId
            ? InstituteUser::where('institute_id', $instituteId)
                ->whereNotNull('qualification')
                ->distinct()
                ->pluck('qualification')
                ->filter()
                ->values()
            : collect();

        $summary = $instituteId ? $this->buildSummary($instituteId) : [
            'total' => 0, 'active' => 0, 'inactive' => 0,
            'assigned' => 0, 'unassigned' => 0, 'by_branch' => [],
        ];

        return view(self::VIEW, [
            'teachers' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'branches' => $branches,
            'designations' => $designations,
            'qualifications' => $qualifications,
            'employmentStatuses' => TeacherProfile::EMPLOYMENT_STATUSES,
            'summary' => $summary,
            'canCreate' => $user->hasPermission('teacher.create'),
        ]);
    }

    private function buildSummary(int $instituteId): array
    {
        $teacherRoleId = \App\Models\Role::where('name', 'Teacher')->first()?->id;
        $query = InstituteUser::where('institute_id', $instituteId)->where('role_id', $teacherRoleId);

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $inactive = $total - $active;
        $assigned = (clone $query)->whereHas('academicAssignments', fn ($q) => $q->where('status', 'active'))->count();
        $unassigned = $total - $assigned;
        $byBranch = (clone $query)->whereNotNull('branch_id')
            ->join('branches', 'institute_users.branch_id', '=', 'branches.id')
            ->selectRaw('branches.name, count(*) as count')
            ->groupBy('branches.name')
            ->pluck('count', 'branches.name')
            ->toArray();

        return compact('total', 'active', 'inactive', 'assigned', 'unassigned', 'byBranch');
    }
}
