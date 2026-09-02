<?php

namespace App\Livewire;

use App\Models\Certificate;
use App\Models\InstituteSetting;
use App\Services\CertificateApprovalModeService;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

class CertificateList extends DataTable
{
    protected const VIEW = 'livewire.certificates.list';

    public array $visibleColumns = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->visibleColumns = $user->preference('columns_certificates', [
            'serial', 'certificate_no', 'type', 'student', 'course', 'batch', 'issue_date', 'status',
        ]);
        $this->visibleColumns = array_values(array_intersect([
            'serial', 'certificate_no', 'type', 'student', 'course', 'batch', 'issue_date', 'status',
        ], $this->visibleColumns));

        $request = request();
        $this->filters = [
            'status' => $request->query('status', ''),
            'branch_id' => $request->query('branch_id', ''),
        ];
        $this->search = $request->query('q', '');

        $this->perPage = 15;
    }

    protected function baseQuery(): Builder
    {
        return Certificate::query()
            ->with('student', 'course', 'batch', 'type')
            ->when(BranchContext::enabled(), function ($query) {
                return $query->whereHas('student', fn ($q) => $q->where('branch_id', BranchContext::id()));
            });
    }

    protected function searchableColumns(): array
    {
        return ['certificate_number', 'student.first_name', 'student.last_name', 'course.name'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'certificate_number', 'issue_date'];
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'status' => $query->where('status', $value),
            'branch_id' => $query->whereHas('student', fn ($q) => $q->where('branch_id', (int) $value)),
            default => null,
        };
    }

    public function getRows(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search
        if (filled($this->search)) {
            $query->where(function (Builder $where) {
                $where->where('certificate_number', 'like', "%{$this->search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%"))
                    ->orWhereHas('course', fn ($course) => $course->where('name', 'like', "%{$this->search}%"));
            });
        }

        // Filters
        if (filled($this->filters['status'] ?? '')) {
            $query->where('status', $this->filters['status']);
        }
        if (filled($this->filters['branch_id'] ?? '')) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', (int) $this->filters['branch_id']));
        }

        $query->latest('id');

        return $query->paginate($this->perPage)->withQueryString();
    }

    public function saveColumns(): void
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'preference')) {
            $user->setPreference('columns_certificates', $this->visibleColumns);
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

        $branches = $instituteId
            ? \App\Models\Branch::where('institute_id', $instituteId)->orderBy('name')->get()
            : collect();

        $certificateTypes = $instituteId
            ? \App\Models\CertificateType::where('institute_id', $instituteId)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $approvalModeService = app(CertificateApprovalModeService::class);
        $certificateApprovalMode = $instituteId ? $approvalModeService->getMode($instituteId) : InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN;

        return view(self::VIEW, [
            'certificates' => $this->getRows(),
            'user' => $user,
            'institute' => $institute,
            'branches' => $branches,
            'certificateTypes' => $certificateTypes,
            'certificateApprovalMode' => $certificateApprovalMode,
            'isAdminControlled' => $certificateApprovalMode === InstituteSetting::CERTIFICATE_APPROVAL_ADMIN,
        ]);
    }
}
