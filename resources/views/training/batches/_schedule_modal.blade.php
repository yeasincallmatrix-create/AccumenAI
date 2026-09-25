@php
    $errors = $errors ?? new \Illuminate\Support\MessageBag();
    $canManage = $user->hasPermission('training_batches.manage');
    $subjects = $subjects ?? collect();
    $scheduleRows = $scheduleRows ?? collect();

    $days = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday'];
    $dayShort = [0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'];
    $palette = ['#1a73e8', '#188038', '#d93025', '#a142f4', '#f9ab00', '#007b83', '#e37400'];

    $todayStr = now()->toDateString();
    $activeToday = $scheduleRows->filter(fn ($r) => ($r['from'] === null || $r['from'] <= $todayStr)
        && ($r['to'] === null || $r['to'] >= $todayStr))->count();

    $px = 54;
@endphp

<div class="modal fade" id="weeklyScheduleModal" tabindex="-1" aria-labelledby="weeklyScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title mb-0" id="weeklyScheduleModalLabel">
                        <i class="bi bi-calendar3 me-1 text-primary"></i>Weekly Schedule
                    </h5>
                    <div class="text-muted small" id="schedSubtitle">
                        {{ $batch->name }}@if ($batch->course) &middot; {{ $batch->course->name }}@endif
                        &middot; {{ $activeToday }} class{{ $activeToday === 1 ? '' : 'es' }}/week
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if ($errors->any())
                    <div class="alert alert-danger py-2 small mb-3">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="schedPrevWeek" title="Previous week">&lsaquo;</button>
                        <button type="button" class="btn btn-outline-secondary" id="schedThisWeek">This week</button>
                        <button type="button" class="btn btn-outline-secondary" id="schedNextWeek" title="Next week">&rsaquo;</button>
                    </div>
                    <input type="date" id="schedWeekDate" class="form-control form-control-sm" style="max-width:170px;"
                           aria-label="Pick any date in the batch period to view"
                           @if ($batch->start_date) min="{{ $batch->start_date->toDateString() }}" @endif
                           @if ($batch->end_date) max="{{ $batch->end_date->toDateString() }}" @endif>
                    <span class="badge bg-light border text-body" id="schedWeekLabel">&nbsp;</span>
                    <span class="badge bg-success-subtle text-success border d-none" id="schedApplyNote"
                          title="Edits made while viewing this week"></span>
                </div>

                @if ($canManage)
                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-primary btn-sm" id="scheduleAddBtn">
                            <i class="bi bi-plus-lg me-1"></i>Add class
                        </button>
                    </div>

                    <div class="card mb-3 d-none" id="scheduleFormCard">
                        <div class="card-body py-3">
                            <form id="scheduleForm" method="POST" action="{{ route('training.batches.schedule.store', $batch->id) }}">
                                @csrf
                                <input type="hidden" name="_method" id="scheduleMethod" value="POST">
                                <input type="hidden" name="edit_id" id="scheduleEditId" value="{{ old('edit_id') }}">
                                <input type="hidden" name="apply_from" id="scheduleApplyFrom" value="">

                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3 col-6">
                                        <label class="form-label small mb-1" for="schedule_subject">Subject</label>
                                        <select name="subject_id" id="schedule_subject" class="form-select form-select-sm">
                                            <option value="">— None —</option>
                                            @foreach ($subjects as $subject)
                                                <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label class="form-label small mb-1" for="schedule_title">Title</label>
                                        <input type="text" name="title" id="schedule_title" class="form-control form-control-sm"
                                               maxlength="150" placeholder="e.g. Practical lab" value="{{ old('title') }}">
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label class="form-label small mb-1" for="schedule_day">Day *</label>
                                        <select name="day_of_week" id="schedule_day" class="form-select form-select-sm" required>
                                            @foreach ($days as $i => $label)
                                                <option value="{{ $i }}" @selected(old('day_of_week') == $i)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label class="form-label small mb-1" for="schedule_start">Start *</label>
                                        <input type="time" name="start_time" id="schedule_start" class="form-control form-control-sm"
                                               required value="{{ old('start_time', '09:00') }}">
                                    </div>
                                    <div class="col-md-1 col-6">
                                        <label class="form-label small mb-1" for="schedule_end">End *</label>
                                        <input type="time" name="end_time" id="schedule_end" class="form-control form-control-sm"
                                               required value="{{ old('end_time', '10:00') }}">
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label class="form-label small mb-1" for="schedule_room">Room</label>
                                        <input type="text" name="room" id="schedule_room" class="form-control form-control-sm"
                                               maxlength="80" placeholder="Room / lab" value="{{ old('room') }}">
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm" id="scheduleSubmit">
                                        <i class="bi bi-plus-lg me-1"></i>Add class
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="scheduleCancel">Cancel edit</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm d-none ms-auto" id="scheduleDelete">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="text-center text-muted py-4 border rounded mb-3 d-none" id="schedEmpty">
                    <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                    No classes scheduled in this week.
                </div>

                <div class="sched-scroll">
                    <div class="sched-grid" data-px="{{ $px }}" data-min-hour="7"
                         style="grid-template-columns:64px repeat(7, minmax(96px,1fr));"></div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <div class="me-auto small text-muted">
                    <span class="d-inline-block sched-dot" style="background:#1a73e8"></span> Plan effective for the selected week
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>

            @if ($canManage)
                <div class="quick-add d-none" id="quickAddOverlay">
                    <div class="quick-add-dialog">
                        <form id="quickAddForm" method="POST" action="{{ route('training.batches.schedule.store', $batch->id) }}">
                            @csrf
                            <input type="hidden" name="day_of_week" id="quickDay" value="0">
                            <input type="hidden" name="apply_from" id="quickApplyFrom" value="">
                            <div class="quick-add-head">
                                <span class="quick-add-day" id="quickDayLabel">Monday</span>
                                <div class="quick-add-times">
                                    <input type="time" name="start_time" id="quickStart" required value="09:00" aria-label="Start time">
                                    <span class="quick-add-sep">&ndash;</span>
                                    <input type="time" name="end_time" id="quickEnd" required value="10:00" aria-label="End time">
                                </div>
                            </div>
                            <div class="quick-add-body">
                                <label class="form-label small mb-1" for="quickSubject">Subject</label>
                                <select name="subject_id" id="quickSubject" class="form-select form-select-sm">
                                    <option value="">— None —</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}" @selected($loop->first)>{{ $subject->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text small mt-1">
                                    Times default to the hour you clicked — adjust above if needed.
                                </div>
                            </div>
                            <div class="quick-add-foot">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickCancel">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm" id="quickOk">OK</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<form id="scheduleDeleteForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
    <input type="hidden" name="apply_from" id="scheduleDeleteApplyFrom" value="">
</form>

@push('styles')
<style>
    .sched-scroll { overflow: auto; max-height: 62vh; border: 1px solid #dee2e6; border-radius: 8px; background: #fff; }
    .sched-grid { display: grid; min-width: 780px; }
    .sched-corner { background: #f8f9fa; border-bottom: 1px solid #dee2e6; position: sticky; top: 0; z-index: 3; }
    .sched-dayhead {
        background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-left: 1px solid #e9ecef;
        text-align: center; font-size: .75rem; font-weight: 600; padding: .45rem .25rem;
        position: sticky; top: 0; z-index: 2;
    }
    .sched-dayhead.is-weekend { background: #f1f3f4; color: #5f6368; }
    .sched-dayhead.is-today { background: #e8f0fe; color: #1a73e8; }
    .sched-gutter { position: relative; border-right: 1px solid #e9ecef; background: #fcfcfd; }
    .sched-hourlabel { position: absolute; right: 7px; font-size: .66rem; color: #80868b; line-height: 1; }
    .sched-col {
        position: relative; border-left: 1px solid #e9ecef;
        background-image: repeating-linear-gradient(to bottom, #eceff1 0, #eceff1 1px, transparent 1px, transparent 100%);
        background-repeat: repeat;
    }
    .sched-col.is-weekend { background-color: #fbfcfd; }
    .sched-event {
        position: absolute; border-radius: 6px; padding: 3px 6px; color: #fff; overflow: hidden;
        font-size: .72rem; line-height: 1.3; border: 1px solid rgba(0,0,0,.06);
        border-left-width: 4px; box-shadow: 0 1px 2px rgba(0,0,0,.14);
    }
    .sched-event.is-editable { cursor: pointer; }
    .sched-event.is-editable:hover { filter: brightness(1.07); box-shadow: 0 3px 8px rgba(0,0,0,.22); }
    .sched-event-time { font-weight: 600; font-size: .68rem; opacity: .95; }
    .sched-event-title { font-weight: 600; }
    .sched-event-meta { font-size: .67rem; opacity: .88; }
    .sched-event.is-compact { padding: 2px 5px; }
    .sched-dot { width: 10px; height: 10px; border-radius: 3px; vertical-align: middle; }
    #schedWeekDate { font-size: .82rem; }

    .quick-add { position: absolute; inset: 0; z-index: 1060; background: rgba(33,37,41,.45); display: flex; align-items: flex-start; justify-content: center; padding-top: 10vh; }
    .quick-add-dialog { width: min(360px, 92%); background: #fff; border-radius: 10px; box-shadow: 0 18px 50px rgba(0,0,0,.35); overflow: hidden; }
    .quick-add-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .75rem; background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
    .quick-add-day { font-weight: 600; font-size: .85rem; }
    .quick-add-times { display: flex; align-items: center; gap: .3rem; }
    .quick-add-times input { width: 96px; padding: .18rem .35rem; font-size: .85rem; border: 1px solid #ced4da; border-radius: 6px; background: #fff; }
    .quick-add-sep { color: #80868b; }
    .quick-add-body { padding: .75rem; }
    .quick-add-foot { display: flex; justify-content: flex-end; gap: .5rem; padding: .6rem .75rem; border-top: 1px solid #e9ecef; background: #fff; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('weeklyScheduleModal');
    if (!modalEl) { return; }

    var canManage = {{ $canManage ? 'true' : 'false' }};
    var updateTpl = @json(route('training.batches.schedule.update', [$batch->id, '__schedule__']));
    var deleteTpl = @json(route('training.batches.schedule.destroy', [$batch->id, '__schedule__']));

    var ROWS = {!! json_encode($scheduleRows->values()->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var dayNames = @json($days);
    var dayShort = @json($dayShort);
    var palette = @json($palette);

    var form = document.getElementById('scheduleForm');
    var formCard = document.getElementById('scheduleFormCard');
    var addBtn = document.getElementById('scheduleAddBtn');
    var submitBtn = document.getElementById('scheduleSubmit');
    var cancelBtn = document.getElementById('scheduleCancel');
    var deleteBtn = document.getElementById('scheduleDelete');
    var methodInput = document.getElementById('scheduleMethod');
    var editInput = document.getElementById('scheduleEditId');
    var deleteForm = document.getElementById('scheduleDeleteForm');
    var storeAction = form ? form.getAttribute('action') : null;
    var currentId = null;

    var quickOverlay = document.getElementById('quickAddOverlay');
    var quickForm = document.getElementById('quickAddForm');
    var quickOk = document.getElementById('quickOk');
    var grid = document.querySelector('#weeklyScheduleModal .sched-grid');
    var emptyEl = document.getElementById('schedEmpty');
    var subtitle = document.getElementById('schedSubtitle');
    var weekLabel = document.getElementById('schedWeekLabel');
    var applyNote = document.getElementById('schedApplyNote');
    var dateInput = document.getElementById('schedWeekDate');

    if (!grid) { return; }

    var px = parseInt(grid.dataset.px, 10) || 54;

    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    function fmtYmd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

    function parseYmd(s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

    function addDays(d, n) { return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n); }

    function mondayOf(dateStr) {
        var d = parseYmd(dateStr);
        return addDays(d, -((d.getDay() + 6) % 7));
    }

    function fmtDM(dateStr) {
        return parseYmd(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function mins(t) { var p = String(t || '0:0').split(':'); return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0); }

    function toTime(hour, minute) {
        if (hour >= 24) { return '23:59'; }
        return pad2(hour) + ':' + pad2(minute || 0);
    }

    function plusHour(value) {
        var parts = (value || '').split(':');
        var minutes = (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0) + 60;
        if (minutes >= 24 * 60) { return '23:59'; }
        return pad2(Math.floor(minutes / 60)) + ':' + pad2(minutes % 60);
    }

    function activeOn(row, dateStr) {
        return (row.from === null || row.from === undefined || row.from <= dateStr)
            && (row.to === null || row.to === undefined || row.to >= dateStr);
    }

    function colorKey(r) {
        if (r.subject_id) { return r.subject_id; }
        var s = String(r.title || r.id), h = 0;
        for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) | 0; }
        return h;
    }

    // ---- week state: bounded by the batch period (start..end) ------------
    var todayStr = fmtYmd(new Date());
    var batchStart = @json($batch->start_date?->toDateString());
    var batchEnd = @json($batch->end_date?->toDateString());
    var navMin = batchStart ? fmtYmd(mondayOf(batchStart)) : null;
    var navMax = batchEnd ? fmtYmd(mondayOf(batchEnd)) : null;
    var todayInRange = (!batchStart || todayStr >= batchStart) && (!batchEnd || todayStr <= batchEnd);
    var preselect = @json(session('schedule_week'));
    var weekStart = mondayOf(preselect || (batchStart && !todayInRange ? batchStart : todayStr));
    if (navMin && fmtYmd(weekStart) < navMin) { weekStart = parseYmd(navMin); }
    if (navMax && fmtYmd(weekStart) > navMax) { weekStart = parseYmd(navMax); }
    var currentApply = weekStart;

    function weekDates() {
        var out = [];
        for (var i = 0; i < 7; i++) { out.push(fmtYmd(addDays(weekStart, i))); }
        return out;
    }

    function syncApplyInputs() {
        ['scheduleApplyFrom', 'quickApplyFrom', 'scheduleDeleteApplyFrom'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = fmtYmd(weekStart); }
        });
    }

    function packLanes(items) {
        items = items.slice().sort(function (a, b) { return mins(a.start) - mins(b.start); });
        var laneEnds = [];
        items.forEach(function (r) {
            var s = mins(r.start), lane = -1, i;
            for (i = 0; i < laneEnds.length; i++) {
                if (laneEnds[i] <= s) { lane = i; break; }
            }
            if (lane < 0) { lane = laneEnds.length; laneEnds.push(0); }
            laneEnds[lane] = mins(r.end);
            r._lane = lane;
        });
        var lc = Math.max(1, laneEnds.length);
        items.forEach(function (r) { r._lanes = lc; });
        return items;
    }

    function chipHtml(r, top, height) {
        var color = palette[Math.abs(colorKey(r)) % palette.length];
        var name = r.title || r.subject || 'Class';
        var tl = r.start + ' – ' + r.end;
        var cls = 'sched-event' + (canManage ? ' is-editable' : '') + (height < 38 ? ' is-compact' : '');
        var meta = (r.room && height >= 56)
            ? '<div class="sched-event-meta"><i class="bi bi-geo-alt me-1"></i>' + esc(r.room) + '</div>' : '';
        return '<div class="' + cls + '" style="top:' + top + 'px;height:' + height
            + 'px;left:calc(' + r._lane + ' * (100% / ' + r._lanes + ') + 3px);width:calc(100% / ' + r._lanes + ' - 6px);background:'
            + color + ';border-left-color:' + color + ';" data-id="' + r.id + '" data-day="' + r.day
            + '" data-start="' + esc(r.start) + '" data-end="' + esc(r.end)
            + '" data-subject="' + (r.subject_id || '') + '" data-title="' + esc(r.title)
            + '" data-room="' + esc(r.room) + '" title="' + esc(tl + ' — ' + name + (r.room ? ' · ' + r.room : '')) + '">'
            + '<div class="sched-event-time">' + esc(tl) + '</div>'
            + '<div class="sched-event-title">' + esc(name) + '</div>' + meta + '</div>';
    }

    function render() {
        var dates = weekDates();
        var byDay = [[], [], [], [], [], [], []];
        var i, h;

        for (i = 0; i < ROWS.length; i++) {
            var r = ROWS[i];
            if (r.day >= 0 && r.day <= 6 && activeOn(r, dates[r.day])) { byDay[r.day].push(r); }
        }

        var minH = 7, maxH = 21, total = 0;
        for (i = 0; i < 7; i++) {
            total += byDay[i].length;
            for (var j = 0; j < byDay[i].length; j++) {
                minH = Math.min(minH, Math.floor(mins(byDay[i][j].start) / 60));
                maxH = Math.max(maxH, Math.floor(mins(byDay[i][j].end) / 60) + 1);
            }
        }
        minH = Math.max(0, minH);
        maxH = Math.min(24, Math.max(minH + 1, maxH));
        var gridH = (maxH - minH) * px;

        var html = ['<div class="sched-corner"></div>'];
        for (i = 0; i < 7; i++) {
            var cls = 'sched-dayhead' + (i >= 5 ? ' is-weekend' : '') + (dates[i] === todayStr ? ' is-today' : '');
            html.push('<div class="' + cls + '">' + dayShort[i] + ' ' + parseYmd(dates[i]).getDate() + '</div>');
        }

        var gut = ['<div class="sched-gutter" style="height:' + gridH + 'px;">'];
        for (h = minH; h < maxH; h++) {
            gut.push('<div class="sched-hourlabel" style="top:' + ((h - minH) * px + 4) + 'px;">' + pad2(h) + ':00</div>');
        }
        gut.push('</div>');
        html.push(gut.join(''));

        for (i = 0; i < 7; i++) {
            var col = ['<div class="sched-col' + (i >= 5 ? ' is-weekend' : '') + '" data-day="' + i
                + '" style="height:' + gridH + 'px;background-size:100% ' + px + 'px;">'];
            packLanes(byDay[i]).forEach(function (r) {
                var top = Math.round((mins(r.start) - minH * 60) / 60 * px);
                var height = Math.max(24, Math.round((mins(r.end) - mins(r.start)) / 60 * px) - 3);
                col.push(chipHtml(r, top, height));
            });
            col.push('</div>');
            html.push(col.join(''));
        }

        grid.innerHTML = html.join('');
        grid.dataset.minHour = minH;

        weekLabel.textContent = 'Week of ' + fmtDM(dates[0]) + ' – ' + fmtDM(dates[6]);
        currentApply = fmtYmd(weekStart);
        syncApplyInputs();
        var floorDate = navMin || todayStr;
        var applyDate = currentApply < floorDate ? floorDate : currentApply;
        if (navMax && applyDate > navMax) { applyDate = navMax; }
        applyNote.textContent = applyDate === todayStr
            ? 'Edits apply from today'
            : 'Edits apply from ' + fmtDM(applyDate);
        applyNote.classList.remove('d-none');
        applyNote.title = 'Edits apply from the selected week of this batch';

        if (emptyEl) { emptyEl.classList.toggle('d-none', total > 0); }
        if (subtitle) {
            var batchLabel = @json(trim($batch->name . ($batch->course ? ' · ' . $batch->course->name : '')));
            subtitle.textContent = batchLabel + ' · ' + total + ' class' + (total === 1 ? '' : 'es') + '/week';
        }
        if (dateInput) {
            var dv = fmtYmd(weekStart);
            if (batchStart && dv < batchStart) { dv = batchStart; }
            if (batchEnd && dv > batchEnd) { dv = batchEnd; }
            if (dateInput.value !== dv) { dateInput.value = dv; }
        }
        if (prevBtn) { prevBtn.disabled = !!navMin && fmtYmd(weekStart) <= navMin; }
        if (nextBtn) { nextBtn.disabled = !!navMax && fmtYmd(weekStart) >= navMax; }
    }

    function goToWeek(monday) {
        var y = fmtYmd(monday);
        if (navMin && y < navMin) { monday = parseYmd(navMin); }
        if (navMax && y > navMax) { monday = parseYmd(navMax); }
        weekStart = monday;
        render();
    }

    if (dateInput) {
        dateInput.addEventListener('change', function () {
            goToWeek(mondayOf(dateInput.value || todayStr));
        });
    }
    var prevBtn = document.getElementById('schedPrevWeek');
    var nextBtn = document.getElementById('schedNextWeek');
    var thisBtn = document.getElementById('schedThisWeek');
    if (prevBtn) { prevBtn.addEventListener('click', function () { goToWeek(addDays(weekStart, -7)); }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { goToWeek(addDays(weekStart, 7)); }); }
    if (thisBtn) {
        if (batchStart && !todayInRange) {
            thisBtn.textContent = 'Batch start';
            thisBtn.addEventListener('click', function () { goToWeek(parseYmd(navMin)); });
        } else {
            thisBtn.addEventListener('click', function () { goToWeek(mondayOf(todayStr)); });
        }
    }

    render();

    // ---- form helpers -----------------------------------------------------
    function openQuickAdd(dayIndex, hour) {
        if (!quickOverlay) { return; }
        syncApplyInputs();
        document.getElementById('quickDay').value = dayIndex;
        document.getElementById('quickDayLabel').textContent = dayNames[dayIndex] || '';
        document.getElementById('quickStart').value = toTime(hour, 0);
        document.getElementById('quickEnd').value = toTime(hour + 1, 0);
        quickOverlay.classList.remove('d-none');
        document.getElementById('quickSubject').focus();
    }

    function closeQuickAdd() {
        if (quickOverlay) { quickOverlay.classList.add('d-none'); }
    }

    function showCard() { if (formCard) { formCard.classList.remove('d-none'); } }

    function resetForm(clearValues) {
        if (!form) { return; }
        currentId = null;
        form.setAttribute('action', storeAction);
        methodInput.value = 'POST';
        editInput.value = '';
        submitBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Add class';
        cancelBtn.classList.add('d-none');
        deleteBtn.classList.add('d-none');
        if (clearValues) {
            form.reset();
            document.getElementById('schedule_start').value = '09:00';
            document.getElementById('schedule_end').value = '10:00';
        }
        syncApplyInputs();
    }

    function enterEdit(id, chip, refill) {
        if (!form) { return; }
        currentId = id;
        showCard();
        if (refill && chip) {
            document.getElementById('schedule_day').value = chip.dataset.day;
            document.getElementById('schedule_start').value = chip.dataset.start;
            document.getElementById('schedule_end').value = chip.dataset.end;
            document.getElementById('schedule_subject').value = chip.dataset.subject || '';
            document.getElementById('schedule_title').value = chip.dataset.title || '';
            document.getElementById('schedule_room').value = chip.dataset.room || '';
        }
        editInput.value = id;
        form.setAttribute('action', updateTpl.replace('__schedule__', id));
        methodInput.value = 'PUT';
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Update class';
        cancelBtn.classList.remove('d-none');
        deleteBtn.classList.remove('d-none');
        formCard.scrollIntoView({ block: 'nearest' });
    }

    if (form) { form.addEventListener('submit', syncApplyInputs); }

    if (canManage) {
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                resetForm(true);
                showCard();
                document.getElementById('schedule_subject').focus();
            });
        }

        grid.addEventListener('click', function (e) {
            var chip = e.target.closest('.sched-event.is-editable');
            if (chip) { enterEdit(chip.dataset.id, chip, true); }
        });

        grid.addEventListener('dblclick', function (e) {
            var col = e.target.closest('.sched-col');
            if (!col || e.target.closest('.sched-event')) { return; }
            var minHour = parseInt(grid.dataset.minHour, 10) || 0;
            var rect = col.getBoundingClientRect();
            var offset = Math.floor((e.clientY - rect.top) / px);
            var hour = Math.max(minHour, Math.min(23, minHour + offset));
            openQuickAdd(parseInt(col.dataset.day, 10), hour);
        });

        if (cancelBtn) { cancelBtn.addEventListener('click', function () { resetForm(true); }); }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (!currentId) { return; }
                if (!confirm('Remove this class from the weekly schedule?')) { return; }
                syncApplyInputs();
                deleteForm.setAttribute('action', deleteTpl.replace('__schedule__', currentId));
                deleteForm.submit();
            });
        }

        if (quickForm) {
            var quickStart = document.getElementById('quickStart');
            var quickEnd = document.getElementById('quickEnd');

            document.getElementById('quickCancel').addEventListener('click', closeQuickAdd);

            quickStart.addEventListener('change', function () {
                if (!quickEnd.value || quickEnd.value <= quickStart.value) {
                    quickEnd.value = plusHour(quickStart.value);
                }
            });

            quickForm.addEventListener('submit', function () {
                syncApplyInputs();
                quickOk.disabled = true;
            });

            quickOverlay.addEventListener('click', function (e) {
                if (e.target === quickOverlay) { closeQuickAdd(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !quickOverlay.classList.contains('d-none')) { closeQuickAdd(); }
            });
        }

        @if (old('edit_id'))
        (function restoreEdit() {
            var chip = grid.querySelector('.sched-event[data-id="{{ old('edit_id') }}"]');
            if (chip) { enterEdit('{{ old('edit_id') }}', chip, true); }
        })();
        @elseif ($errors->any())
        showCard();
        @endif
    }

    @if ($errors->any() || session('schedule_open'))
    document.addEventListener('DOMContentLoaded', function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    @endif
})();
</script>
@endpush
