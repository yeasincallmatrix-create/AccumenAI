<div>
@php
    $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null);
    $statusBadge = [
        'active'    => 'text-bg-success',
        'inactive'  => 'text-bg-secondary',
        'suspended' => 'text-bg-danger',
    ];
@endphp

<div class="standalone-heading">
    <h4>{{ $isProfessional ? 'Trainers & Instructors' : 'Teachers & Instructors' }}</h4>
    <p>{{ $isProfessional ? 'Manage trainer profiles, employment status and training assignments.' : 'Manage instructor profiles, employment status and teaching assignments.' }}</p>
    @if ($canCreate)
        <a href="{{ route('teachers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ $isProfessional ? 'New Trainer' : 'New Teacher' }}</a>
    @endif
</div>

<div class="row g-2 mb-3">
    <div class="col-md-2"><div class="summary-box"><span class="summary-value">{{ $summary['total'] }}</span><span class="summary-label">Total</span></div></div>
    <div class="col-md-2"><div class="summary-box"><span class="summary-value">{{ $summary['active'] }}</span><span class="summary-label">Active</span></div></div>
    <div class="col-md-2"><div class="summary-box"><span class="summary-value">{{ $summary['inactive'] }}</span><span class="summary-label">Inactive</span></div></div>
    <div class="col-md-2"><div class="summary-box"><span class="summary-value">{{ $summary['assigned'] }}</span><span class="summary-label">Assigned</span></div></div>
    <div class="col-md-2"><div class="summary-box"><span class="summary-value">{{ $summary['unassigned'] }}</span><span class="summary-label">Unassigned</span></div></div>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.350ms="search" placeholder="Name, code, email or phone">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Branch</label>
                <select class="form-select form-select-sm" wire:model.live="filters.branch_id">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Employment</label>
                <select class="form-select form-select-sm" wire:model.live="filters.employment_status">
                    <option value="">All</option>
                    @foreach ($employmentStatuses as $employmentStatus)
                        <option value="{{ $employmentStatus }}">{{ ucwords(str_replace('_', ' ', $employmentStatus)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Designation</label>
                <select class="form-select form-select-sm" wire:model.live="filters.designation">
                    <option value="">All designations</option>
                    @foreach ($designations as $designation)
                        <option value="{{ $designation }}">{{ $designation }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters">Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Staff UID</th>
                    <th>Employee ID</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Branch</th>
                    <th>Designation</th>
                    <th>Employment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($teachers as $teacher)
                    <tr>
                        <td class="text-muted">{{ $teachers->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">
                            <a class="text-decoration-none" href="{{ route('teachers.show', $teacher) }}">{{ $teacher->first_name }} {{ $teacher->last_name }}</a>
                        </td>
                        <td>
                            @php $teacherUid = $teacher->user->uid ?? null; if(!$teacherUid){ $foundUser = \App\Models\User::where('email', $teacher->email)->first(); $teacherUid = $foundUser?->uid; } @endphp
                            @if($teacherUid)
                                <x-uid-with-copy :uid="$teacherUid" />
                            @elseif($teacher->uuid)
                                <x-uid-with-copy :uid="\Illuminate\Support\Str::substr($teacher->uuid,0,6)" label="UUID" />
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $teacher->employee_id ?? '—' }}</td>
                        <td>{{ $teacher->phone ?? '—' }}</td>
                        <td>{{ $teacher->email ?? '—' }}</td>
                        <td>{{ $teacher->branch?->name ?? '—' }}</td>
                        <td>{{ $teacher->designation ?? '—' }}</td>
                        <td>{{ $teacher->teacherProfile?->employment_status ? ucwords(str_replace('_', ' ', $teacher->teacherProfile->employment_status)) : '—' }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$teacher->status] ?? 'text-bg-secondary' }}">{{ ucfirst($teacher->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">{{ $isProfessional ? 'No trainers found.' : 'No teachers found.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $teachers->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $teachers->total() }} {{ $isProfessional ? 'trainers' : 'teachers' }}</span>
    </div>
</div>
</div>