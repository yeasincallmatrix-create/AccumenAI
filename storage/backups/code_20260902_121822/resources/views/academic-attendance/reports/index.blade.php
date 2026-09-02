@extends('layouts.institute')

@section('title', 'Attendance Reports — AccumenAI')

@section('content')
<div class="page-header">
    <div>
        <h4 class="page-header-title">
            <i class="bi bi-clipboard-data me-1 text-primary"></i>Attendance Reports
        </h4>
        <p class="text-muted mb-0">Read-only attendance analytics computed live from the institute's attendance ledger. Each report is printable and can be downloaded as a CSV export.</p>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <i class="bi bi-mortarboard fs-2 text-primary"></i>
                    <span class="badge text-bg-light border">Per student</span>
                </div>
                <h5 class="fw-semibold">Student Attendance Report</h5>
                <p class="text-muted small mb-3">
                    Attendance summary and day-by-day records for one student across an optional academic year, resolved against the placement that was active on each date.
                </p>
                <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-person me-1"></i>Open a student first
                </a>
                <div class="text-muted small mt-3">
                    <i class="bi bi-info-circle me-1"></i>Also available as "View Attendance Report" on a student's Academic History page.
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <i class="bi bi-people fs-2 text-primary"></i>
                    <span class="badge text-bg-light border">Class / Group</span>
                </div>
                <h5 class="fw-semibold">Class / Group Report</h5>
                <p class="text-muted small mb-3">
                    Per-student attendance totals (present / absent / late / leave) for an academic year, class and optional group, over a date range.
                </p>
                <a href="{{ route('academic-attendance.reports.class') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-clipboard-data me-1"></i>Open Class Report
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <i class="bi bi-calendar-check fs-2 text-primary"></i>
                    <span class="badge text-bg-light border">Per date</span>
                </div>
                <h5 class="fw-semibold">Daily Attendance Report</h5>
                <p class="text-muted small mb-3">
                    The attendance status of every placed student for one date, with class/group and per-status totals. Unmarked students are shown separately, never counted as absent.
                </p>
                <a href="{{ route('academic-attendance.reports.daily') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-calendar2-week me-1"></i>Open Daily Report
                </a>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="p-3 small text-muted">
        <i class="bi bi-shield-check me-1"></i>
        All reports require the <code>attendance.manage</code> permission and are tenant + branch scoped. Report figures are computed directly from the
        attendance ledger and never write to any table.
    </div>
</div>
@endsection