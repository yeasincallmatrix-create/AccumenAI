<div>
<div class="standalone-heading">
    <h4>Student Finance</h4>
    <p>Billed, collected, outstanding and overdue totals per student. Open a student to view the full ledger, generate invoices, record payments and approve waivers/refunds.</p>
    <a href="{{ route('finance.education.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Education Finance</a>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Name or student ID">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th class="text-end">Invoices</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Collected</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Overdue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td>
                            <a href="{{ route('finance.education.students.show', $student->id) }}" class="text-decoration-none">{{ $student->first_name }} {{ $student->last_name }}</a>
                        </td>
                        <td class="text-muted">{{ $student->student_id }}</td>
                        <td class="text-end">{{ $student->invoice_count }}</td>
                        <td class="text-end">{{ number_format((float) $student->billed, 2) }}</td>
                        <td class="text-end text-success">{{ number_format((float) $student->collected, 2) }}</td>
                        <td class="text-end fw-semibold {{ (float) $student->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format((float) $student->outstanding, 2) }}</td>
                        <td class="text-end {{ (float) $student->overdue > 0 ? 'text-danger' : '' }}">{{ number_format((float) $student->overdue, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No students with billing activity found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($students->hasPages())
        <div class="p-2 border-top d-flex flex-column align-items-center gap-2">
            {{ $students->links('pagination::bootstrap-5') }}
            <span class="text-muted small">{{ $students->total() }} students</span>
        </div>
    @endif
</div>
</div>
