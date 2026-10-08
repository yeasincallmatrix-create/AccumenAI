@extends('layouts.institute')

@section('title', 'Appointments — AccumenAI')

@section('body_class', 'page-fit')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Appointments (OPD)</h4>
    </div>
    <div class="page-header-actions">
        @if(!empty($queue['doctorId']))
            <a class="btn btn-outline-danger"
               href="{{ route('medical.appointments.live', ['doctor' => $queue['doctorId']]) }}"
               target="_blank" rel="noopener"
               title="Live Broadcast — open the fullscreen live broadcast in a new tab"
               aria-label="Live Broadcast">
                <i class="bi bi-broadcast"></i>
            </a>
        @endif
        <a href="{{ route('medical.queue.display.selector') }}" class="btn btn-outline-primary" title="Fullscreen queue board">
            <i class="bi bi-tv me-1"></i>Queue Display
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
            <i class="bi bi-plus-lg me-1"></i>Book Appointment
        </button>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link {{ ($activeTab ?? 'appointments') === 'appointments' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#pane-appointments" role="tab">
            <i class="bi bi-calendar-event me-1"></i>Appointments
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link {{ ($activeTab ?? '') === 'queue' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#pane-queue" role="tab">
            <i class="bi bi-people me-1"></i>Live Queue
        </button>
    </li>
    @if($canViewPrescriptions ?? false)
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link {{ ($activeTab ?? '') === 'prescriptions' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#pane-prescriptions" role="tab">
            <i class="bi bi-file-medical me-1"></i>Prescriptions
        </button>
    </li>
    @endif
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link {{ ($activeTab ?? '') === 'report' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#pane-report" role="tab">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Report
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link {{ ($activeTab ?? '') === 'audit' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#pane-audit" role="tab">
            <i class="bi bi-clock-history me-1"></i>Audit Log
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ ($activeTab ?? 'appointments') === 'appointments' ? 'show active' : '' }}" id="pane-appointments" role="tabpanel">
        <div class="card card-fill-screen">
            <div class="card-body">
                <div id="react-appointments-container" data-props='@json($reactProps ?? [])'></div>
                <noscript>
                    <div class="alert alert-warning mb-0">The appointments list needs JavaScript enabled.</div>
                </noscript>
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ ($activeTab ?? '') === 'queue' ? 'show active' : '' }}" id="pane-queue" role="tabpanel">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('medical.appointments.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="queue">
                    <div class="col-md-4">
                        <label class="form-label" for="queue_doctor">Doctor</label>
                        <select id="queue_doctor" name="q_doctor" class="form-select" onchange="guardTdateSubmit(this)">
                            @forelse($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected((string) ($queue['doctorId'] ?? '') === (string) $doctor->id)>
                                    {{ $doctor->name }} ({{ $queue['doctorTotals'][$doctor->id] ?? 0 }})
                                </option>
                            @empty
                                <option value="">No doctors available</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="queue_date">Date</label>
                        <x-tdate-input name="q_date" :value="$queue['date'] ?? date('Y-m-d')" id="queue_date" class="form-control" onchange="guardTdateSubmit(this)" />
                    </div>
                    <div class="col-md-5 text-end">
                        <span class="badge bg-primary fs-6 me-2" data-queue-badge="total">Total: {{ $queue['status']['total'] }}</span>
                        <span class="badge bg-warning text-dark fs-6 me-2" data-queue-badge="waiting">Waiting: {{ $queue['status']['waiting'] }}</span>
                        <span class="badge bg-info text-dark fs-6 me-2" data-queue-badge="checked_in">Checked In: {{ $queue['status']['checked_in'] }}</span>
                        <span class="badge bg-success fs-6 me-2" data-queue-badge="in_progress">In Progress: {{ $queue['status']['in_progress'] }}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Refresh queue"
                                onclick="if (window.refreshQueueWidget) { window.refreshQueueWidget(); } else { window.location.reload(); }">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-fill-screen">
            <div class="card-header">
                <h6 class="mb-0" data-queue-wait-title>Queue — {{ $queue['status']['estimated_wait_minutes'] ?? 0 }} mins estimated wait</h6>
            </div>
            <div class="card-body">
                @if(!empty($queue['doctorId']))
                    @livewire('medical.queue-manager', ['doctorUserId' => (int) $queue['doctorId'], 'date' => $queue['date'], 'instituteId' => $instituteId], key('medical-queue-'.$queue['doctorId'].'-'.$queue['date']))
                @else
                    <div class="empty-fill text-muted">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        No doctor selected.
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($canViewPrescriptions ?? false)
    <div class="tab-pane fade {{ ($activeTab ?? '') === 'prescriptions' ? 'show active' : '' }}" id="pane-prescriptions" role="tabpanel">
        <div class="card card-fill-screen">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0"><i class="bi bi-file-medical me-1"></i>Prescriptions</h6>
                <a class="btn btn-sm btn-primary" href="{{ route('medical.prescriptions.create') }}">
                    <i class="bi bi-plus-lg me-1"></i>Write Prescription
                </a>
            </div>
            <div class="card-body">
                <div id="react-prescriptions-container" data-props='@json(['initialPrescriptions' => [], 'refreshUrl' => route('medical.prescriptions.react.data')])'></div>
                <noscript>
                    <div class="alert alert-warning mb-0">The prescriptions list needs JavaScript enabled.</div>
                </noscript>
            </div>
        </div>
    </div>
    @endif

    <div class="tab-pane fade {{ ($activeTab ?? '') === 'report' ? 'show active' : '' }}" id="pane-report" role="tabpanel">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('medical.appointments.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="report">
                    <div class="col-md-3">
                        <label class="form-label" for="report_doctor">Doctor</label>
                        <select id="report_doctor" name="r_doctor" class="form-select" onchange="this.form.submit()" title="Filter report by doctor">
                            <option value="">All Doctors</option>
                            @foreach(($report['doctors'] ?? $doctors ?? []) as $reportDoctor)
                                <option value="{{ $reportDoctor->id }}" @selected((string) ($report['doctorId'] ?? '') === (string) $reportDoctor->id)>{{ $reportDoctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="report_from">From</label>
                        <x-tdate-input name="r_from" :value="$report['from'] ?? date('Y-m-d')" id="report_from" class="form-control" onchange="guardTdateSubmit(this)" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="report_to">To</label>
                        <x-tdate-input name="r_to" :value="$report['to'] ?? date('Y-m-d')" id="report_to" class="form-control" onchange="guardTdateSubmit(this)" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="report_search">Patient</label>
                        <input type="text" id="report_search" name="r_search" class="form-control" placeholder="Search patient name or phone…" value="{{ $report['search'] ?? '' }}">
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a href="{{ route('medical.appointments.index', ['tab' => 'report']) }}" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-primary fs-6">Total Visits: {{ $report['totalVisits'] ?? 0 }}</span>
                <span class="badge bg-info text-dark fs-6">Total Fee: ৳{{ number_format((float) ($report['totalFee'] ?? 0), 2) }}</span>
                <span class="badge bg-warning text-dark fs-6">Discount: ৳{{ number_format((float) ($report['totalDiscount'] ?? 0), 2) }}</span>
                <span class="badge bg-success fs-6">Fee Collected: ৳{{ number_format((float) ($report['totalCollected'] ?? 0), 2) }}</span>
            </div>
        </div>

        @if(!empty($report['perDoctor']))
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-1"></i>Doctor-wise Totals</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th class="text-end">Total Visits</th>
                                    <th class="text-end">Fee</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Fee Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['perDoctor'] as $summary)
                                    <tr>
                                        <td class="fw-semibold">{{ $summary['doctor'] }}</td>
                                        <td class="text-end">{{ $summary['visits'] }}</td>
                                        <td class="text-end">৳{{ number_format((float) $summary['fee'], 2) }}</td>
                                        <td class="text-end">৳{{ number_format((float) ($summary['discount'] ?? 0), 2) }}</td>
                                        <td class="text-end fw-semibold">৳{{ number_format((float) $summary['fee_collected'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card card-fill-screen">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-1"></i>Visit Report</h6>
            </div>
            <div class="card-body">
                @if(empty($report['rows']))
                    <div class="empty-fill text-muted">
                        <i class="bi bi-clipboard-data fs-2 d-block mb-2"></i>
                        No visits found for this filter.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient Name</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Visit Type</th>
                                    <th class="text-end">Fee</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Fee Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['rows'] as $i => $visit)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $visit['patient'] }}</td>
                                        <td>{{ $visit['doctor'] }}</td>
                                        <td>{{ $visit['date_display'] }}</td>
                                        <td>
                                            @if(($visit['visit_type'] ?? '—') === 'Follow-up')
                                                <span class="badge text-bg-info">Follow-up</span>
                                            @elseif(($visit['visit_type'] ?? '—') === 'First visit')
                                                <span class="badge text-bg-primary">First visit</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $visit['fee'] !== null ? '৳'.number_format((float) $visit['fee'], 2) : '—' }}</td>
                                        <td class="text-end">{{ ($visit['discount'] ?? null) !== null && (float) $visit['discount'] > 0 ? '৳'.number_format((float) $visit['discount'], 2) : '—' }}</td>
                                        <td class="text-end fw-semibold">{{ $visit['fee_collected'] !== null ? '৳'.number_format((float) $visit['fee_collected'], 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Showing {{ count($report['rows']) }} visits (cancelled excluded).</p>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ ($activeTab ?? '') === 'audit' ? 'show active' : '' }}" id="pane-audit" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>Audit Log</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('medical.appointments.index') }}" class="row g-2 mb-3">
                    <input type="hidden" name="tab" value="audit">
                    <input type="hidden" name="q_doctor" value="{{ $queue['doctorId'] }}">
                    <input type="hidden" name="q_date" value="{{ $queue['date'] }}">
                    <div class="col-md-3">
                        <select name="q_audit_doctor" class="form-select" onchange="this.form.submit()" title="Filter audit log by doctor">
                            @foreach(($queue['auditDoctors'] ?? []) as $auditDoctor)
                                <option value="{{ $auditDoctor->id }}" @selected((int) ($queue['auditDoctorId'] ?? 0) === (int) $auditDoctor->id)>{{ $auditDoctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="q_action" class="form-select" onchange="this.form.submit()">
                            <option value="">All actions</option>
                            @foreach(($queue['auditActions'] ?? []) as $value => $label)
                                <option value="{{ $value }}" @selected(($queue['auditAction'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="q_actor" class="form-control" placeholder="Search by user..." value="{{ $queue['auditActor'] ?? '' }}">
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a href="{{ route('medical.appointments.index', ['tab' => 'audit', 'q_doctor' => $queue['doctorId'], 'q_date' => $queue['date']]) }}" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
                @if(empty($queue['auditLogs']))
                    <p class="text-muted text-center py-4 mb-0">No audit actions logged yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Action</th>
                                    <th>Detail</th>
                                    <th>Received By</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($queue['auditLogs'] as $log)
                                    <tr>
                                        <td class="fw-semibold">{{ $log['patient'] }}</td>
                                        <td>{{ $log['doctor'] ?? 'N/A' }}</td>
                                        <td>
                                            @if(($log['action'] ?? 'reorder') === 'fee_collected')
                                                <span class="badge text-bg-success">Fee Collected</span>
                                            @elseif(($log['action'] ?? '') === 'fee_reversed')
                                                <span class="badge text-bg-danger">Fee Reversed</span>
                                            @elseif(($log['action'] ?? '') === 'deleted')
                                                <span class="badge text-bg-dark">Deleted</span>
                                            @elseif(($log['action'] ?? '') === 'rollover')
                                                <span class="badge text-bg-info">Carried Forward</span>
                                            @elseif(($log['action'] ?? '') === 'auto_cancelled')
                                                <span class="badge text-bg-secondary">Auto-cancelled</span>
                                            @else
                                                <span class="badge text-bg-primary">Reorder</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(in_array($log['action'] ?? '', ['fee_collected', 'fee_reversed'], true))
                                                <span class="fw-semibold">৳{{ number_format((float) ($log['amount'] ?? 0), 2) }}</span>
                                                @if(($log['action'] ?? '') === 'fee_collected' && !empty($log['needs_verification']))
                                                    <span class="text-warning small d-block">(payment needs to be verified)</span>
                                                @endif
                                            @elseif(($log['action'] ?? '') === 'deleted')
                                                <span class="text-muted small">Record removed</span>
                                            @elseif(($log['action'] ?? '') === 'rollover')
                                                <span class="badge text-bg-light border">#{{ $log['old'] ?? '—' }} → #{{ $log['new'] }}</span>
                                                <span class="text-muted small">next working day</span>
                                            @elseif(($log['action'] ?? '') === 'auto_cancelled')
                                                <span class="text-muted small">Never checked in</span>
                                            @else
                                                <span class="badge text-bg-light border">#{{ $log['old'] ?? '—' }} → #{{ $log['new'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $log['received_by'] ?? $log['actor'] }} <span class="badge text-bg-secondary">@if($log['user_type'] === 'owner'){{ $queue['orgWord'] ?? 'Institute' }} Owner@else{{ ucwords(str_replace(['-', '_'], ' ', $log['user_type'])) }}@endif</span></td>
                                        <td class="text-muted small">{{ $log['at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Showing latest {{ count($queue['auditLogs']) }} audit actions{{ ($queue['auditAction'] ?? '') !== '' || ($queue['auditActor'] ?? '') !== '' ? ' (filtered)' : '' }}.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@include('medical.patients._quick_create_modal')
@include('medical.appointments._book_modal')
@include('medical.appointments._transfer_modal')
@include('medical.appointments._fee_modal')
@include('medical.appointments._vitals_modal')
@endsection

@push('styles')
<style>
/* Fixed shell for this page: the document itself never scrolls. The header,
   tab bar and card stack are laid out against the viewport and only the
   active pane scrolls its own content, so the layout adapts to any screen. */
body.page-fit { height: 100vh; height: 100dvh; overflow: hidden; }
body.page-fit .layout { height: 100vh; height: 100dvh; min-height: 0; }
body.page-fit main.content {
    height: 100vh;
    height: 100dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-sizing: border-box;
}
body.page-fit main.content > * { flex: 0 0 auto; min-height: 0; }
body.page-fit main.content > .tab-content { flex: 1 1 auto; min-height: 0; }
body.page-fit .tab-content > .tab-pane.active {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
}
body.page-fit .tab-content > .tab-pane.active > .card { flex: 0 0 auto; }
body.page-fit .tab-content > .tab-pane.active > .card-fill-screen {
    flex: 1 1 auto;
    min-height: 180px;
}

/* Appointments + Live Queue tabs: cards stretch to fill the screen height
   even when there is no data; empty states sit centered in the free space. */
.card-fill-screen {
    min-height: max(360px, calc(100vh - 270px));
    min-height: max(360px, calc(100dvh - 270px));
    display: flex;
    flex-direction: column;
}
body.page-fit .card-fill-screen { min-height: 180px; }
.card-fill-screen > .card-body {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
body.page-fit .card-fill-screen > .card-body {
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
}
.card-fill-screen > .card-body > div {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
body.page-fit .card-fill-screen > .card-body > div { min-height: max-content; }
.card-fill-screen .empty-fill {
    margin: auto;
    text-align: center;
    padding: 2.5rem 1rem;
}
/* Dragged-row elevation in the React appointments list (mirrors the
   Livewire queue dragging style). Separate border model so cell shadows
   and rounded ends actually paint (collapse suppresses both); the row
   silhouette shadow is a drop-shadow filter on the <tr> itself. */
#react-appointments-container .table {
    border-collapse: separate;
    border-spacing: 0;
}
#react-appointments-container tr.dragging {
    filter: drop-shadow(0 4px 10px rgba(15,23,42,.18));
}
html.monetix-dark #react-appointments-container tr.dragging {
    filter: drop-shadow(0 4px 10px rgba(0,0,0,.45));
}
#react-appointments-container tr.dragging td {
    background: #fff;
    position: relative;
    z-index: 10;
    transform: translateY(-4px) scale(1.01);
    transition: transform .2s ease;
    color: #000;
}
#react-appointments-container tr.dragging td a,
#react-appointments-container tr.dragging td .text-muted {
    color: #000 !important;
}
#react-appointments-container tr.dragging td:first-child {
    border-radius: 12px 0 0 12px;
    border-left: 4px solid var(--bs-primary);
}
#react-appointments-container tr.dragging td:last-child {
    border-radius: 0 12px 12px 0;
}
html.monetix-dark #react-appointments-container tr.dragging td {
    background: #fff;
    box-shadow: 0 18px 38px rgba(0,0,0,.5), 0 8px 18px rgba(0,0,0,.4);
}
@media (prefers-reduced-motion: reduce) {
    #react-appointments-container tr.dragging td { transform: none; }
}
</style>
@endpush

@push('scripts')
    @viteReactRefresh
    @vite('resources/js/medical/appointments.jsx')
    @if($canViewPrescriptions ?? false)
        @vite('resources/js/medical/prescriptions.jsx')
    @endif
<script>
// Live queue header sync: the queue widget (Livewire) owns the table, but
// the counters + wait-time header are rendered outside it. After every
// queue action the widget dispatches `queue-updated` with fresh counts —
// update them in place so no manual page refresh is needed.
(function () {
    if (window.__queueHeaderSyncBound === '1') return;
    window.__queueHeaderSyncBound = '1';

    var labels = { total: 'Total', waiting: 'Waiting', checked_in: 'Checked In', in_progress: 'In Progress' };

    function setBadge(name, value) {
        var el = document.querySelector('[data-queue-badge="' + name + '"]');
        if (el) el.textContent = labels[name] + ': ' + value;
    }

    document.addEventListener('queue-updated', function (e) {
        var status = (e && e.detail && (e.detail.status || e.detail[0])) || null;
        // Livewire may deliver params as {status: {...}} or [ {...} ].
        if (Array.isArray(status)) status = status[0];
        if (!status) return;
        var waiting = 0;
        var waitingEl = document.querySelector('[data-queue-badge="waiting"]');
        if (waitingEl) {
            var m = /(\d+)/.exec(waitingEl.textContent || '');
            // Waiting (scheduled) never changes via queue actions; keep the
            // server value, but recompute Total = waiting + live in-queue.
            waiting = m ? parseInt(m[1], 10) : 0;
        }
        setBadge('waiting', waiting);
        setBadge('checked_in', status.checked_in || 0);
        setBadge('in_progress', status.in_progress || 0);
        setBadge('total', waiting + (status.total || 0));
        var title = document.querySelector('[data-queue-wait-title]');
        if (title) title.textContent = 'Queue — ' + (status.estimated_wait_minutes || 0) + ' mins estimated wait';
    });
})();

@if(!empty($autoFee))
<script>
// Post-visit doctor flow landing: prescription just saved — open the fee
// popup automatically; confirming completes the visit. The collect endpoint
// re-validates everything server-side.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof openFeeModal !== 'function') return;
    var data = @json($autoFee);
    openFeeModal({ getAttribute: function (k) { return data[k] || ''; } });
});
</script>
@endif
@endpush
