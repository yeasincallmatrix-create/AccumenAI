@extends('layouts.institute')

@section('title', 'Timetable — AccumenAI')

@section('content')
@push('styles')
<style>
    .timetable-grid { overflow-x: auto; }
    .timetable-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .timetable-table th, .timetable-table td { border: 1px solid #dee2e6; padding: 6px 8px; vertical-align: top; min-width: 140px; }
    .timetable-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 1; }
    .timetable-table .time-col { min-width: 80px; text-align: center; background: #f8f9fa; font-weight: 500; }
    .timetable-table .day-col { min-width: 160px; }
    .timetable-table .event-cell { border-radius: 4px; padding: 4px 6px; margin-bottom: 4px; font-size: 0.8rem; }
    .timetable-table .event-cell .ev-title { font-weight: 600; }
    .timetable-table .event-cell .ev-meta { opacity: 0.8; font-size: 0.75rem; }
</style>
@endpush

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('calendar.timetable') }}</h4>
        <p class="page-header-desc mb-0">{{ mawa_e('calendar.weekly_overview', ['batch' => \Carbon\Carbon::parse($startDate)->format('j M') . ' – ' . \Carbon\Carbon::parse($endDate)->format('j M Y')]) }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar me-1"></i>{{ mawa_e('calendar.title') }}</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="admin-card">
    <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('calendar.timetable') }}">
        <div class="col-md-2">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.from_date') }}</label>
            <input type="date" class="form-control form-control-sm" name="start_date" value="{{ $startDate }}">
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.to_date') }}</label>
            <input type="date" class="form-control form-control-sm" name="end_date" value="{{ $endDate }}">
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.branch') }}</label>
            <select class="form-select form-select-sm" name="branch_id">
                <option value="">{{ mawa_e('calendar.all') }}</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected(($filters['branch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.batch') }}</label>
            <select class="form-select form-select-sm" name="batch_id">
                <option value="">{{ mawa_e('calendar.all') }}</option>
                @foreach ($batches as $b)
                    <option value="{{ $b->id }}" @selected(($filters['batch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.teacher') }}</label>
            <select class="form-select form-select-sm" name="teacher_id">
                <option value="">{{ mawa_e('calendar.all') }}</option>
                @foreach ($teachers as $t)
                    <option value="{{ $t->id }}" @selected(($filters['teacher_id'] ?? '') == $t->id)>{{ $t->first_name }} {{ $t->last_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label mb-1 small">{{ mawa_e('calendar.room') }}</label>
            <select class="form-select form-select-sm" name="room_id">
                <option value="">{{ mawa_e('calendar.all') }}</option>
                @foreach ($rooms as $r)
                    <option value="{{ $r->id }}" @selected(($filters['room_id'] ?? '') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">{{ mawa_e('calendar.view') }}</button>
        </div>
    </form>

    @php
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }
        $timeSlots = collect();
        $allEvents = $timetable->flatten();
        if ($allEvents->isNotEmpty()) {
            $earliest = $allEvents->whereNotNull('start_time')->min('start_time') ?? '08:00';
            $latest = $allEvents->whereNotNull('end_time')->max('end_time') ?? '18:00';
            $hour = (int) substr($earliest, 0, 2);
            $endHour = (int) substr($latest, 0, 2);
            while ($hour <= $endHour) {
                $timeSlots->push(sprintf('%02d:00', $hour));
                $hour++;
            }
        }
        if ($timeSlots->isEmpty()) {
            $timeSlots = collect(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00']);
        }
    @endphp

    <div class="timetable-grid">
        <table class="timetable-table">
            <thead>
                <tr>
                    <th class="time-col">{{ mawa_e('calendar.time') }}</th>
                    @foreach ($days as $day)
                        <th class="day-col" style="{{ $day->isToday() ? 'background:#e7f1ff;' : '' }}">
                            {{ $day->format('D') }}<br><span class="fw-normal">{{ $day->format('j M') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($timeSlots as $slot)
                    @php
                        $slotHour = (int) substr($slot, 0, 2);
                        $slotNext = sprintf('%02d:00', $slotHour + 1);
                    @endphp
                    <tr>
                        <td class="time-col">{{ $slot }}</td>
                        @foreach ($days as $day)
                            @php
                                $dayKey = $day->format('Y-m-d');
                                $dayEvents = $timetable->get($dayKey, collect());
                                $slotEvents = $dayEvents->filter(function ($e) use ($slotHour, $slotNext, $dayKey) {
                                    if ($e->is_all_day) return true;
                                    $startH = $e->start_time ? (int) substr($e->start_time, 0, 2) : 0;
                                    $endH = $e->end_time ? (int) ceil(((int) substr($e->end_time, 0, 2)) + (substr($e->end_time, 3, 2) > 0 ? 1 : 0)) : 24;
                                    return $startH <= $slotHour && $endH > $slotHour;
                                });
                            @endphp
                            <td style="{{ $day->isToday() ? 'background:#f0f7ff;' : '' }}">
                                @forelse ($slotEvents as $event)
                                    <div class="event-cell" style="background: {{ match($event->event_type) {
                                        'exam' => '#f8d7da',
                                        'practical', 'viva' => '#e8daef',
                                        'holiday' => '#d4edda',
                                        'meeting' => '#e2e3e5',
                                        default => '#d6eaf8',
                                    } }}; border-left: 3px solid {{ match($event->event_type) {
                                        'exam' => '#dc3545',
                                        'practical', 'viva' => '#6f42c1',
                                        'holiday' => '#198754',
                                        'meeting' => '#6c757d',
                                        default => '#3788d8',
                                    } }};">
                                        @if ($event->is_all_day)
                                            <div class="ev-title">{{ mawa_e('calendar.all_day') }}</div>
                                        @else
                                            <div class="ev-title">{{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time ?? '', 0, 5) }}</div>
                                        @endif
                                        <a href="{{ route('calendar.events.show', $event) }}" class="text-decoration-none text-dark">
                                            {{ \Illuminate\Support\Str::limit($event->title, 40) }}
                                        </a>
                                        @if ($event->subject)
                                            <div class="ev-meta">{{ $event->subject->name }}</div>
                                        @endif
                                        @if ($event->teacher)
                                            <div class="ev-meta">{{ $event->teacher->first_name }}</div>
                                        @endif
                                        @if ($event->room)
                                            <div class="ev-meta"><i class="bi bi-door-open"></i> {{ $event->room->name }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($allEvents->isEmpty())
        <div class="text-center text-muted py-4">{{ mawa_e('calendar.no_events') }}</div>
    @endif
</div>
@endsection
