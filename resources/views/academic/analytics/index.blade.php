@extends('layouts.institute')

@section('title', 'Education Analytics — ' . ($subIndustryLabel ?: $industryLabel))

@section('content')

@php
    $academic = $overview['academic'];
    $finance = $overview['finance'];
    $crm = $overview['crm'];
    $year = $academic['year'];
    $attendance = $academic['attendance'];
    $isSchool = in_array($subIndustry, ['school','primary_school','secondary_high_school','school_college','madrasha'], true);
    $isVocational = in_array($subIndustry, ['vocational_institute','technical_training_center','skill_development_center','computer_it_training_institute','professional_training_academy'], true);
    $isCollegeUni = in_array($subIndustry, ['college','university','institution'], true);
    $aReport = $attendanceReport ?? ['valid'=>false,'buckets'=>collect(),'classes'=>collect(),'totals'=>[]];
    $rReport = $resultReport ?? ['results'=>collect(),'classes'=>collect(),'totals'=>[]];
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Education Analytics</h4>
        <p class="page-header-desc">
            {{ $institute->name ?? '' }} — {{ $industryLabel }}@if($subIndustryLabel) <span class="text-muted">· {{ $subIndustryLabel }}</span>@endif
            <span class="badge bg-light text-dark border ms-2">{{ $academic['overview']['students_total'] ?? 0 }} students · {{ $academic['overview']['batches_total'] ?? 0 }} batches</span>
        </p>
        @if($isSchool)
            <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>School view: attendance & class results emphasized.</p>
        @elseif($isVocational)
            <p class="small text-muted mb-0"><i class="bi bi-tools me-1"></i>Vocational view: completion & certificates emphasized.</p>
        @elseif($isCollegeUni)
            <p class="small text-muted mb-0"><i class="bi bi-mortarboard me-1"></i>Higher-ed view: graduation & published results emphasized.</p>
        @endif
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-light border p-2">
            <i class="bi bi-calendar-check me-1"></i>
            Current year: {{ $year ? $year->name : 'not set' }}
        </span>
        <a href="{{ route('academic.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Operations</a>
    </div>
</div>

{{-- Filters --}}
<div class="admin-card mb-3">
    <form method="GET" action="{{ route('academic.analytics.index') }}" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Academic Year</label>
            <select name="academic_year_id" class="form-select form-select-sm">
                <option value="">Current year</option>
                @foreach($options['years'] as $y)
                    <option value="{{ $y->id }}" {{ (string)($filters['academic_year_id'] ?? '') === (string)$y->id ? 'selected' : '' }}>{{ $y->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Branch</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">All branches</option>
                @foreach($options['branches'] as $b)
                    <option value="{{ $b->id }}" {{ (string)($filters['branch_id'] ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Class / Grade</label>
            <select name="class_grade_id" class="form-select form-select-sm">
                <option value="">All classes</option>
                @foreach($options['classes'] as $c)
                    <option value="{{ $c->id }}" {{ (string)($filters['class_grade_id'] ?? '') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Group</label>
            <select name="academic_group_id" class="form-select form-select-sm">
                <option value="">All groups</option>
                @foreach($options['groups'] as $g)
                    <option value="{{ $g->id }}" {{ (string)($filters['academic_group_id'] ?? '') === (string)$g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Apply</button>
            <a href="{{ route('academic.analytics.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

@if ($year === null)
    <div class="alert alert-warning py-2 small" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> No current academic year is configured. Overview uses year-scoped figures where applicable; set one in <a href="{{ route('settings.index') }}">Settings → Academic</a>.
    </div>
@endif

{{-- Top KPI cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(13,110,253,.1); color:#0d6efd;"><i class="bi bi-people-fill"></i></div>
            <div class="num">{{ $academic['students']['cohort'] }}</div>
            <div class="label">Active Students <span class="text-muted">/ {{ $academic['overview']['students_total'] ?? 0 }} total</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;"><i class="bi bi-journal-check"></i></div>
            <div class="num">{{ $academic['results']['published_results'] }}</div>
            <div class="label">Published Results <span class="text-muted">· {{ $academic['results']['passed_students'] }}/{{ $academic['results']['passed_students'] + $academic['results']['failed_students'] }}</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-award-fill"></i></div>
            <div class="num">{{ $academic['certificates']['eligible'] }}</div>
            <div class="label">Certificate Eligible <span class="text-muted">/ {{ $academic['certificates']['issued'] }} issued</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(13,110,253,.1); color:#0d6efd;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="num">{{ $attendance['present_percent'] !== null ? number_format($attendance['present_percent'], 1) . '%' : '—' }}</div>
            <div class="label">Attendance % <span class="text-muted">· {{ $attendance['total'] ?? 0 }} rec.</span></div>
        </div>
    </div>
</div>

{{-- Charts row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-graph-up"></i> Attendance trend — present % ({{ $aReport['period'] ?? 'month' }})</div>
                <span class="badge bg-light text-dark border">{{ $aReport['buckets']->count() }} buckets</span>
            </div>
            @if(($aReport['valid'] ?? false) && $aReport['buckets']->isNotEmpty())
                <canvas id="analyticsAttendanceChart" height="140" data-json='@json($aReport['buckets']->map(fn($b)=>['label'=>$b['label'],'percent'=>$b['present_percent'],'total'=>$b['total']])->values())'></canvas>
                <div class="text-muted small mt-2">Window: {{ optional($aReport['start'])->format('d M Y') }} → {{ optional($aReport['end'])->format('d M Y') }} · by {{ $aReport['period'] }}</div>
            @else
                <div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i> {{ $aReport['message'] ?? $attendance['message'] ?? 'No attendance window.' }} Set dates or academic year.</div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-pie-chart-fill"></i> Results — pass vs fail</div>
            </div>
            @php
                $passed = $rReport['totals']['passed'] ?? $academic['results']['passed_students'] ?? 0;
                $failed = $rReport['totals']['failed'] ?? $academic['results']['failed_students'] ?? 0;
            @endphp
            @if(($passed + $failed) > 0)
                <div class="d-flex justify-content-center py-2">
                    <canvas id="analyticsResultsChart" width="180" height="180" data-passed="{{ $passed }}" data-failed="{{ $failed }}"></canvas>
                </div>
                <div class="d-flex gap-2 justify-content-center small">
                    <span class="badge bg-success">Passed {{ $passed }}</span>
                    <span class="badge bg-danger">Failed {{ $failed }}</span>
                    <span class="badge bg-light text-dark border">{{ $passed+$failed>0 ? round($passed/($passed+$failed)*100,1) : 0 }}% pass</span>
                </div>
            @else
                <div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i>No published results in this scope.</div>
            @endif
            @if($finance !== null)
                <hr class="my-3">
                <div class="toolbar-info small mb-2"><i class="bi bi-cash-coin me-1"></i>Finance donut</div>
                <div class="d-flex justify-content-center">
                    <canvas id="analyticsFinanceChart" width="180" height="140" data-receivable="{{ $finance['receivable'] }}" data-payable="{{ $finance['payable'] }}" data-net="{{ $finance['net_income'] }}"></canvas>
                </div>
                <div class="row row-cols-3 g-2 small mt-2 text-center">
                    <div class="col"><div class="border rounded p-2">Recv<br><strong>{{ number_format($finance['receivable'],0) }}</strong></div></div>
                    <div class="col"><div class="border rounded p-2">Pay<br><strong>{{ number_format($finance['payable'],0) }}</strong></div></div>
                    <div class="col"><div class="border rounded p-2 {{ $finance['net_income']<0?'text-danger':'' }}">Net<br><strong>{{ number_format($finance['net_income'],0) }}</strong></div></div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Structure overview --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-people-fill"></i> Students</div><a href="{{ route('academic.analytics.students', request()->query()) }}" class="btn btn-sm btn-outline-primary">Details</a></div>
            <div class="row row-cols-3 g-2">
                @foreach ([['key' => 'active', 'label' => 'Active'], ['key' => 'completed', 'label' => 'Completed'], ['key' => 'graduated', 'label' => 'Graduated'], ['key' => 'withdrawn', 'label' => 'Withdrawn/Dropped'], ['key' => 'transferred', 'label' => 'Transferred']] as $metric)
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">{{ $metric['label'] }}</div><div class="fw-semibold">{{ $academic['students'][$metric['key']] }}</div></div></div>
                @endforeach
                <div class="col"><div class="border rounded p-2 h-100 text-center border-success"><div class="text-muted small">Cohort</div><div class="fw-semibold text-primary">{{ $academic['students']['cohort'] }}</div></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> Promotion</div><a href="{{ route('academic.analytics.promotions') }}" class="btn btn-sm btn-outline-primary">Details</a></div>
            <div class="row row-cols-3 g-2">
                @foreach ([['key' => 'pending', 'label' => 'Pending'], ['key' => 'review', 'label' => 'In Review'], ['key' => 'approved', 'label' => 'Approved']] as $metric)
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">{{ $metric['label'] }}</div><div class="fs-6 fw-semibold">{{ $academic['promotion'][$metric['key']] }}</div></div></div>
                @endforeach
                <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Passed</div><div class="fw-semibold text-success">{{ $academic['results']['passed_students'] }}</div></div></div>
                <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Failed</div><div class="fw-semibold text-danger">{{ $academic['results']['failed_students'] }}</div></div></div>
                <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Cert. Issued</div><div class="fw-semibold text-success">{{ $academic['certificates']['issued'] }}</div></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-calendar-check-fill"></i> Attendance</div><a href="{{ route('academic.analytics.attendance', request()->query()) }}" class="btn btn-sm btn-outline-primary">Details</a></div>
            @if ($attendance['available'])
                <div class="row row-cols-2 g-2">
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Records</div><div class="fw-semibold">{{ $attendance['total'] }}</div></div></div>
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Present %</div><div class="fw-semibold text-success">{{ $attendance['present_percent'] !== null ? number_format($attendance['present_percent'], 1) . '%' : '—' }}</div></div></div>
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Absent</div><div class="fw-semibold text-danger">{{ $attendance['absent'] }}</div></div></div>
                    <div class="col"><div class="border rounded p-2 h-100 text-center"><div class="text-muted small">Late / Leave</div><div class="fw-semibold">{{ $attendance['late'] }} / {{ $attendance['leave'] }}</div></div></div>
                </div>
                <canvas id="analyticsAttendanceMini" height="60" class="mt-3 d-none" aria-hidden="true"></canvas>
            @else
                <div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i> {{ $attendance['message'] }}</div>
            @endif
        </div>
    </div>
</div>

@if ($academic['overview'] || $academic['academics'])
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-layers-fill"></i> Academic Structure</div></div>
            <div class="row row-cols-3 row-cols-md-4 g-2 small">
                @foreach(['levels'=>'Levels','classes'=>'Classes','groups'=>'Groups','subjects'=>'Subjects','systems'=>'Systems','grading_scales'=>'Scales','institute_levels'=>'Inst. Levels','institute_classes'=>'Inst. Classes'] as $k=>$label)
                    <div class="col"><div class="border rounded p-2 text-center"><div class="text-muted">{{ $label }}</div><div class="fw-semibold fs-6">{{ $academic['academics'][$k] ?? 0 }}</div></div></div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-person-workspace"></i> Teachers & Batches</div></div>
            <div class="row row-cols-2 g-2 small">
                <div class="col"><div class="border rounded p-2 text-center"><div class="text-muted">Teachers</div><div class="fw-semibold">{{ $academic['teachers']['total'] ?? 0 }} <span class="text-success">({{ $academic['teachers']['active'] ?? 0 }} act)</span></div></div></div>
                <div class="col"><div class="border rounded p-2 text-center"><div class="text-muted">Batches</div><div class="fw-semibold">{{ $academic['batches']['by_status']['running'] ?? 0 }} / {{ $academic['overview']['batches_total'] ?? 0 }}</div></div></div>
                <div class="col"><div class="border rounded p-2 text-center"><div class="text-muted">Courses</div><div class="fw-semibold">{{ $academic['courses']['assigned'] ?? 0 }} / {{ $academic['courses']['total'] ?? 0 }}</div></div></div>
                <div class="col"><div class="border rounded p-2 text-center"><div class="text-muted">Exams</div><div class="fw-semibold">{{ $academic['overview']['exams_total'] ?? 0 }}</div></div></div>
            </div>
        </div>
    </div>
</div>
@endif

@if ($finance !== null)
    <div class="admin-card mb-4">
        <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-cash-coin"></i> Finance Summary <span class="text-muted small ms-2">— billed / outstanding context</span></div><a href="{{ route('academic.analytics.finance', request()->query()) }}" class="btn btn-sm btn-outline-primary">Full report</a></div>
        <div class="row row-cols-2 row-cols-md-4 g-2">
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Receivable</div><div class="fs-6 fw-semibold">{{ number_format($finance['receivable'], 2) }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Payable</div><div class="fs-6 fw-semibold">{{ number_format($finance['payable'], 2) }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Net Income</div><div class="fs-6 fw-semibold {{ $finance['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($finance['net_income'], 2) }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Net Receivable</div><div class="fs-6 fw-semibold">{{ number_format($finance['net'], 2) }}</div></div></div>
        </div>
    </div>
@endif

@if ($crm !== null)
    <div class="admin-card mb-4">
        <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-people-fill"></i> CRM → Admission funnel</div><a href="{{ route('academic.analytics.crm') }}" class="btn btn-sm btn-outline-primary">Full report</a></div>
        <div class="row row-cols-2 row-cols-md-4 g-2">
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Contacts</div><div class="fs-6 fw-semibold">{{ $crm['contacts'] }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Leads</div><div class="fs-6 fw-semibold">{{ $crm['leads'] }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Open / Won</div><div class="fs-6 fw-semibold">{{ $crm['open'] }} / {{ $crm['won'] }}</div></div></div>
            <div class="col"><div class="border rounded p-3 h-100 text-center"><div class="text-muted small">Converted (rate)</div><div class="fs-6 fw-semibold text-success">{{ $crm['converted'] }} ({{ $crm['conversion_rate'] !== null ? number_format($crm['conversion_rate'], 1) . '%' : '—' }})</div></div></div>
        </div>
    </div>
@endif

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-bar-chart-line-fill"></i> Related topics — drill into a report</div>
        <span class="text-muted small">Education only · adapts by sub-industry</span>
    </div>
    <div class="row g-3">
        @foreach ([
            ['route' => 'academic.analytics.students', 'icon' => 'bi-people-fill', 'title' => 'Student Analytics', 'desc' => $isVocational ? 'By skill group & year; placement + promotion + attendance + certificate.' : 'Searchable roster with placement, promotion, result & attendance.'],
            ['route' => 'academic.analytics.courses', 'icon' => 'bi-journal-bookmark-fill', 'title' => 'Course Analytics', 'desc' => 'Per-course cohort, completion, pass rate & attendance.'],
            ['route' => 'academic.analytics.batches', 'icon' => 'bi-collection-fill', 'title' => 'Batch Analytics', 'desc' => 'Per-batch cohort & completion, tied to course.'],
            ['route' => 'academic.analytics.attendance', 'icon' => 'bi-calendar-check-fill', 'title' => 'Attendance Analytics', 'desc' => $isSchool ? 'Daily window & class breakdown — ideal for school/madrasha.' : 'Window totals, weekly/monthly buckets & class breakdown.'],
            ['route' => 'academic.analytics.results', 'icon' => 'bi-clipboard-check-fill', 'title' => 'Result Analytics', 'desc' => $isCollegeUni ? 'Published frozen snapshots & pass heatmap.' : 'Published final-result pass/fail rates.'],
            ['route' => 'academic.analytics.promotions', 'icon' => 'bi-diagram-3-fill', 'title' => 'Promotion Analytics', 'desc' => 'Decision statuses & approved outcomes per year.'],
            ['route' => 'academic.analytics.completion', 'icon' => 'bi-mortarboard-fill', 'title' => 'Completion & Exit', 'desc' => $isVocational ? 'Skill completion & exit tracking.' : 'Cohort completion, graduation, drop & transfer rates.'],
            ['route' => 'academic.analytics.certificates', 'icon' => 'bi-patch-check-fill', 'title' => 'Certificate Analytics', 'desc' => 'Issued / revoked / pending by course.'],
        ] as $report)
            <div class="col-md-3">
                <a href="{{ route($report['route'], request()->query()) }}" class="text-decoration-none">
                    <div class="border rounded p-3 h-100 analytics-tile">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi {{ $report['icon'] }} fs-5 text-primary"></i>
                            <span class="fw-semibold text-dark">{{ $report['title'] }}</span>
                        </div>
                        <div class="text-muted small">{{ $report['desc'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
        @if ($finance !== null)
            <div class="col-md-3">
                <a href="{{ route('academic.analytics.finance', request()->query()) }}" class="text-decoration-none">
                    <div class="border rounded p-3 h-100 analytics-tile">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-cash-coin fs-5 text-success"></i>
                            <span class="fw-semibold text-dark">Finance &amp; Education</span>
                        </div>
                        <div class="text-muted small">Billed / outstanding & overdue by course & batch — branch-aware.</div>
                    </div>
                </a>
            </div>
        @endif
        @if ($crm !== null)
            <div class="col-md-3">
                <a href="{{ route('academic.analytics.crm', request()->query()) }}" class="text-decoration-none">
                    <div class="border rounded p-3 h-100 analytics-tile">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-diagram-2-fill fs-5 text-warning"></i>
                            <span class="fw-semibold text-dark">CRM → Admission</span>
                        </div>
                        <div class="text-muted small">Lead pipeline, statuses & conversion to admission.</div>
                    </div>
                </a>
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/analytics-charts.js') }}?v={{ file_exists(public_path('js/analytics-charts.js')) ? filemtime(public_path('js/analytics-charts.js')) : time() }}"></script>
@endpush
