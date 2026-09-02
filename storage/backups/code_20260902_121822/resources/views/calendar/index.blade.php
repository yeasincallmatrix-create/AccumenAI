@extends('layouts.institute')

@section('title', 'Calendar — AccumenAI')

@section('content')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<style>
    .fc { font-family: inherit; }
    .fc .fc-toolbar-title { font-size: 1.2rem; font-weight: 600; }
    .fc .fc-button { font-size: 0.85rem; padding: 4px 12px; }
    .fc .fc-button-active { background-color: #0d6efd !important; border-color: #0d6efd !important; }
    .fc .fc-event { border-radius: 4px; padding: 1px 4px; font-size: 0.8rem; cursor: pointer; }
    .fc .fc-daygrid-event { margin-bottom: 1px; }
    .fc .fc-timegrid-event { border-radius: 4px; }
    .fc .fc-col-header-cell { font-size: 0.85rem; font-weight: 600; }
    .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 16px; }
    .filter-bar .form-select, .filter-bar .form-control { font-size: 0.85rem; }
    .event-type-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; }
</style>
@endpush

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('calendar.title') }}</h4>
        <p class="page-header-desc mb-0">{{ mawa_e('calendar.description') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('calendar.timetable', $filters) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-table me-1"></i>{{ mawa_e('calendar.timetable') }}
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createEventModal">
            <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('calendar.add_event') }}
        </button>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="admin-card">
    <form class="filter-bar" method="GET" action="{{ route('calendar.index') }}">
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.branch') }}</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_branches') }}</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected(($filters['branch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.course') }}</label>
            <select name="course_id" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_courses') }}</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.batch') }}</label>
            <select name="batch_id" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_batches') }}</option>
                @foreach ($batches as $b)
                    <option value="{{ $b->id }}" @selected(($filters['batch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.teacher') }}</label>
            <select name="teacher_id" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_teachers') }}</option>
                @foreach ($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_id'] ?? '') == $t->id)>{{ $t->first_name }} {{ $t->last_name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.event_type') }}</label>
            <select name="event_type" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_types') }}</option>
                @foreach ($eventTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['event_type'] ?? '') == $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1 1 140px;">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.room') }}</label>
            <select name="room_id" class="form-select form-select-sm">
                <option value="">{{ mawa_e('calendar.all_rooms') }}</option>
                @foreach ($rooms as $r)
                    <option value="{{ $r->id }}" @selected(($filters['room_id'] ?? '') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="view" value="{{ $view }}">
        <div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>{{ mawa_e('calendar.filter') }}</button>
            <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary btn-sm">{{ mawa_e('calendar.reset') }}</a>
        </div>
    </form>

    <div id="calendar"></div>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('calendar.events.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ mawa_e('calendar.create_event') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ mawa_e('calendar.title_label') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ mawa_e('calendar.event_type') }} <span class="text-danger">*</span></label>
                            <select class="form-select" name="event_type" required>
                                @foreach ($eventTypes as $type)
                                    <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ mawa_e('calendar.description_label') }}</label>
                            <textarea class="form-control" name="description" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.start_date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.start_time') }}</label>
                            <input type="time" class="form-control" name="start_time" value="09:00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.end_date') }}</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.end_time') }}</label>
                            <input type="time" class="form-control" name="end_time" value="10:00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.all_day') }}</label>
                            <select class="form-select" name="is_all_day">
                                <option value="0">{{ mawa_e('calendar.no') }}</option>
                                <option value="1">{{ mawa_e('calendar.yes') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.branch') }}</label>
                            <select class="form-select" name="branch_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.course') }}</label>
                            <select class="form-select" name="course_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.subject') }}</label>
                            <select class="form-select" name="subject_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($subjects as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.batch') }}</label>
                            <select class="form-select" name="batch_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.teacher') }}</label>
                            <select class="form-select" name="teacher_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.room') }}</label>
                            <select class="form-select" name="room_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($rooms as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.academic_year') }}</label>
                            <select class="form-select" name="academic_year_id">
                                <option value="">{{ mawa_e('calendar.none') }}</option>
                                @foreach ($academicYears as $y)
                                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><hr><h6>{{ mawa_e('calendar.recurrence') }}</h6></div>
                        <div class="col-md-4">
                            <label class="form-label">{{ mawa_e('calendar.frequency') }}</label>
                            <select class="form-select" name="recurrence_rule[frequency]" id="recurrenceFrequency">
                                <option value="">{{ mawa_e('calendar.no_repeat') }}</option>
                                <option value="daily">{{ mawa_e('calendar.daily') }}</option>
                                <option value="weekly">{{ mawa_e('calendar.weekly') }}</option>
                                <option value="monthly">{{ mawa_e('calendar.monthly') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ mawa_e('calendar.every') }}</label>
                            <input type="number" class="form-control" name="recurrence_rule[interval]" value="1" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ mawa_e('calendar.end_date') }}</label>
                            <input type="date" class="form-control" name="recurrence_rule[end_date]">
                        </div>
                        <div class="col-md-3" id="daysOfWeekGroup" style="display:none;">
                            <label class="form-label">{{ mawa_e('calendar.days_of_week') }}</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (['mon' => mawa_e('calendar.mon'), 'tue' => mawa_e('calendar.tue'), 'wed' => mawa_e('calendar.wed'), 'thu' => mawa_e('calendar.thu'), 'fri' => mawa_e('calendar.fri'), 'sat' => mawa_e('calendar.sat'), 'sun' => mawa_e('calendar.sun')] as $val => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recurrence_rule[days_of_week][]" value="{{ $val }}" id="dow{{ $val }}">
                                        <label class="form-check-label" for="dow{{ $val }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('calendar.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('calendar.create_event') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Event Detail Modal -->
<div class="modal fade" id="eventDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventDetailTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailBody"></div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-primary btn-sm" id="eventDetailLink">{{ mawa_e('calendar.view_details') }}</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ mawa_e('calendar.close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
(function () {
    var calLabels = {
        type: @json(mawa_e('calendar.type')),
        date: @json(mawa_e('calendar.date')),
        time: @json(mawa_e('calendar.time')),
        course: @json(mawa_e('calendar.course')),
        subject: @json(mawa_e('calendar.subject')),
        batch: @json(mawa_e('calendar.batch')),
        teacher: @json(mawa_e('calendar.teacher')),
        room: @json(mawa_e('calendar.room')),
        branch: @json(mawa_e('calendar.branch')),
        confirmMove: @json(mawa_e('calendar.confirm_move')),
        updateFailed: @json(mawa_e('calendar.update_failed')),
        confirmResize: @json(mawa_e('calendar.confirm_resize'))
    };
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) { return; }

    var initialView = '{{ $view === "agenda" ? "listWeek" : ($view === "day" ? "dayGridDay" : ($view === "week" ? "dayGridWeek" : "dayGridMonth")) }}';
    var initialDate = '{{ $date }}';

    var params = new URLSearchParams(window.location.search);
    var qs = params.toString();

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: initialView,
        initialDate: initialDate,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridDay,dayGridWeek,dayGridMonth,listWeek'
        },
        events: '/calendar/events/json' + (qs ? '?' + qs : ''),
        editable: true,
        droppable: true,
        selectable: true,
        nowIndicator: true,
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            var props = info.event.extendedProps;
            document.getElementById('eventDetailTitle').textContent = info.event.title;
            var html = '<table class="table table-sm mb-0">';
            html += '<tr><td class="text-muted" style="width:120px">' + calLabels.type + '</td><td>' + (props.event_type || '—') + '</td></tr>';
            html += '<tr><td class="text-muted">' + calLabels.date + '</td><td>' + info.event.start.toLocaleDateString() + '</td></tr>';
            if (!info.event.allDay) {
                html += '<tr><td class="text-muted">' + calLabels.time + '</td><td>' + info.event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' – ' + (info.event.end ? info.event.end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '—') + '</td></tr>';
            }
            if (props.course) { html += '<tr><td class="text-muted">' + calLabels.course + '</td><td>' + props.course + '</td></tr>'; }
            if (props.subject) { html += '<tr><td class="text-muted">' + calLabels.subject + '</td><td>' + props.subject + '</td></tr>'; }
            if (props.batch) { html += '<tr><td class="text-muted">' + calLabels.batch + '</td><td>' + props.batch + '</td></tr>'; }
            if (props.teacher) { html += '<tr><td class="text-muted">' + calLabels.teacher + '</td><td>' + props.teacher + '</td></tr>'; }
            if (props.room) { html += '<tr><td class="text-muted">' + calLabels.room + '</td><td>' + props.room + '</td></tr>'; }
            if (props.branch) { html += '<tr><td class="text-muted">' + calLabels.branch + '</td><td>' + props.branch + '</td></tr>'; }
            html += '</table>';
            document.getElementById('eventDetailBody').innerHTML = html;
            document.getElementById('eventDetailLink').href = '/calendar/' + info.event.id;
            new bootstrap.Modal(document.getElementById('eventDetailModal')).show();
        },
        eventDrop: function (info) {
            if (!confirm(calLabels.confirmMove.replace(':title', info.event.title).replace(':date', info.event.start.toLocaleDateString()))) {
                info.revert();
                return;
            }
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('/calendar/' + info.event.id, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-HTTP-Method-Override': 'PUT'
                },
                body: JSON.stringify({
                    start_date: info.event.startStr.split('T')[0],
                    start_time: info.event.startStr.includes('T') ? info.event.startStr.split('T')[1].substring(0, 5) : null,
                    end_date: info.event.endStr ? info.event.endStr.split('T')[0] : null,
                    end_time: info.event.endStr && info.event.endStr.includes('T') ? info.event.endStr.split('T')[1].substring(0, 5) : null,
                    _method: 'PUT'
                })
            }).then(function (r) {
                if (!r.ok) { alert(calLabels.updateFailed); info.revert(); }
            });
        },
        eventResize: function (info) {
            if (!confirm(calLabels.confirmResize.replace(':title', info.event.title))) {
                info.revert();
                return;
            }
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('/calendar/' + info.event.id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-HTTP-Method-Override': 'PUT'
                },
                body: JSON.stringify({
                    end_date: info.event.endStr ? info.event.endStr.split('T')[0] : null,
                    end_time: info.event.endStr && info.event.endStr.includes('T') ? info.event.endStr.split('T')[1].substring(0, 5) : null,
                    _method: 'PUT'
                })
            }).then(function (r) {
                if (!r.ok) { alert(calLabels.updateFailed); info.revert(); }
            });
        },
        dateClick: function (info) {
            var modal = document.getElementById('createEventModal');
            var startInput = modal.querySelector('[name="start_date"]');
            if (startInput) { startInput.value = info.dateStr; }
            new bootstrap.Modal(modal).show();
        }
    });

    calendar.render();

    document.getElementById('recurrenceFrequency').addEventListener('change', function () {
        document.getElementById('daysOfWeekGroup').style.display = this.value === 'weekly' ? '' : 'none';
    });
})();
</script>
@endpush
