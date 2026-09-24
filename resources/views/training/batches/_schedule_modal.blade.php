@php
    $errors = $errors ?? new \Illuminate\Support\MessageBag();
    $canManage = $user->hasPermission('training_batches.manage');
    $subjects = $subjects ?? collect();

    $days = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday'];
    $dayShort = [0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'];
    $palette = ['#1a73e8', '#188038', '#d93025', '#a142f4', '#f9ab00', '#007b83', '#e37400'];

    $schedules = $batch->schedules;
    $byDay = $schedules->groupBy('day_of_week');

    $minH = 7;
    $maxH = 21;
    foreach ($schedules as $s) {
        $minH = min($minH, intdiv($s->start_minutes, 60));
        $maxH = max($maxH, intdiv($s->end_minutes, 60) + 1);
    }
    $minH = max(0, $minH);
    $maxH = min(24, max($minH + 1, $maxH));
    $hours = range($minH, $maxH - 1);
    $px = 54;
    $gridHeight = count($hours) * $px;

    $layout = [];
    foreach ($byDay as $items) {
        $laneEnds = [];
        foreach ($items as $s) {
            $lane = null;
            foreach ($laneEnds as $li => $end) {
                if ($end <= $s->start_minutes) {
                    $lane = $li;
                    break;
                }
            }
            if ($lane === null) {
                $lane = count($laneEnds);
            }
            $laneEnds[$lane] = $s->end_minutes;
            $layout[$s->id] = ['lane' => $lane];
        }
        $lanes = max(1, count($laneEnds));
        foreach ($items as $s) {
            $layout[$s->id]['lanes'] = $lanes;
        }
    }
@endphp

<div class="modal fade" id="weeklyScheduleModal" tabindex="-1" aria-labelledby="weeklyScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title mb-0" id="weeklyScheduleModalLabel">
                        <i class="bi bi-calendar3 me-1 text-primary"></i>Weekly Schedule
                    </h5>
                    <div class="text-muted small">
                        {{ $batch->name }}@if ($batch->course) &middot; {{ $batch->course->name }}@endif
                        &middot; {{ $schedules->count() }} class{{ $schedules->count() === 1 ? '' : 'es' }}/week
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

                @if ($canManage)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Double-click an empty hour to add a class — click a block to edit it.
                            <span class="d-block d-lg-inline">Changes apply from today; earlier dates keep the plan they had.</span>
                        </div>
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

                @if ($schedules->isEmpty())
                    <div class="text-center text-muted py-4 border rounded mb-3">
                        <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                        No classes scheduled yet for this batch.
                    </div>
                @endif

                <div class="sched-scroll">
                    <div class="sched-grid" data-px="{{ $px }}" data-min-hour="{{ $minH }}"
                         style="grid-template-columns:64px repeat(7, minmax(96px,1fr));">
                        <div class="sched-corner"></div>
                        @foreach ($days as $i => $label)
                            <div class="sched-dayhead {{ in_array($i, [5, 6]) ? 'is-weekend' : '' }}">{{ $dayShort[$i] }}</div>
                        @endforeach

                        <div class="sched-gutter" style="height:{{ $gridHeight }}px;">
                            @foreach ($hours as $h)
                                <div class="sched-hourlabel" style="top:{{ ($h - $minH) * $px + 4 }}px;">{{ sprintf('%02d:00', $h) }}</div>
                            @endforeach
                        </div>

                        @foreach ($days as $i => $label)
                            <div class="sched-col {{ in_array($i, [5, 6]) ? 'is-weekend' : '' }}"
                                 data-day="{{ $i }}"
                                 style="height:{{ $gridHeight }}px;background-size:100% {{ $px }}px;">
                                @foreach ($byDay[$i] ?? [] as $s)
                                    @php
                                        $top = (int) round(($s->start_minutes - $minH * 60) / 60 * $px);
                                        $height = max(24, (int) round(($s->end_minutes - $s->start_minutes) / 60 * $px) - 3);
                                        $lane = $layout[$s->id]['lane'];
                                        $lanes = $layout[$s->id]['lanes'];
                                        $colorKey = $s->subject_id ?: crc32((string) ($s->title ?: $s->id));
                                        $color = $palette[abs($colorKey) % count($palette)];
                                        $name = $s->title ?: ($s->subject?->name ?? 'Class');
                                        $timeLabel = $s->start_label . ' – ' . $s->end_label;
                                    @endphp
                                    <div class="sched-event {{ $canManage ? 'is-editable' : '' }} {{ $height < 38 ? 'is-compact' : '' }}"
                                         style="top:{{ $top }}px;height:{{ $height }}px;left:calc({{ $lane }} * (100% / {{ $lanes }}) + 3px);width:calc(100% / {{ $lanes }} - 6px);background:{{ $color }};border-left-color:{{ $color }};"
                                         data-id="{{ $s->id }}"
                                         data-day="{{ $s->day_of_week }}"
                                         data-start="{{ $s->start_label }}"
                                         data-end="{{ $s->end_label }}"
                                         data-subject="{{ $s->subject_id }}"
                                         data-title="{{ $s->title }}"
                                         data-room="{{ $s->room }}"
                                         title="{{ $timeLabel }} — {{ $name }}{{ $s->room ? ' · ' . $s->room : '' }}">
                                        <div class="sched-event-time">{{ $timeLabel }}</div>
                                        <div class="sched-event-title">{{ $name }}</div>
                                        @if ($s->room && $height >= 56)
                                            <div class="sched-event-meta"><i class="bi bi-geo-alt me-1"></i>{{ $s->room }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <div class="me-auto small text-muted">
                    <span class="d-inline-block sched-dot" style="background:#1a73e8"></span> Weekly repeating schedule
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>

            @if ($canManage)
                <div class="quick-add d-none" id="quickAddOverlay">
                    <div class="quick-add-dialog">
                        <form id="quickAddForm" method="POST" action="{{ route('training.batches.schedule.store', $batch->id) }}">
                            @csrf
                            <input type="hidden" name="day_of_week" id="quickDay" value="0">
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
    var dayLabels = @json($days);
    var grid = document.querySelector('#weeklyScheduleModal .sched-grid');
    var pxPerHour = grid ? parseInt(grid.dataset.px, 10) || 54 : 54;
    var minHour = grid ? parseInt(grid.dataset.minHour, 10) || 7 : 7;

    function pad2(n) { return (n < 10 ? '0' : '') + n; }

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

    function openQuickAdd(dayIndex, hour) {
        if (!quickOverlay) { return; }
        document.getElementById('quickDay').value = dayIndex;
        document.getElementById('quickDayLabel').textContent = dayLabels[dayIndex] || '';
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
    }

    function enterEdit(id, chip, refill) {
        if (!form) { return; }
        currentId = id;
        showCard();
        if (refill) {
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

    if (canManage) {
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                resetForm(true);
                showCard();
                document.getElementById('schedule_subject').focus();
            });
        }

        document.querySelectorAll('.sched-event.is-editable').forEach(function (chip) {
            chip.addEventListener('click', function () { enterEdit(chip.dataset.id, chip, true); });
        });

        if (cancelBtn) { cancelBtn.addEventListener('click', function () { resetForm(true); }); }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (!currentId) { return; }
                if (!confirm('Remove this class from the weekly schedule?')) { return; }
                deleteForm.setAttribute('action', deleteTpl.replace('__schedule__', currentId));
                deleteForm.submit();
            });
        }

        document.querySelectorAll('#weeklyScheduleModal .sched-col').forEach(function (col) {
            col.addEventListener('dblclick', function (e) {
                if (e.target.closest('.sched-event')) { return; }
                var rect = col.getBoundingClientRect();
                var offset = Math.floor((e.clientY - rect.top) / pxPerHour);
                var hour = Math.max(minHour, Math.min(23, minHour + offset));
                openQuickAdd(parseInt(col.dataset.day, 10), hour);
            });
        });

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
            var chip = document.querySelector('.sched-event[data-id="{{ old('edit_id') }}"]');
            if (chip) { enterEdit('{{ old('edit_id') }}', chip, false); }
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
