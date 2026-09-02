@extends('layouts.institute')

@section('title', $event->title . ' — Calendar')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $event->title }}</h4>
        <p class="page-header-desc mb-0">
            <span class="badge bg-primary me-1">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
            @if ($event->status === 'cancelled')
                <span class="badge bg-danger">{{ mawa_e('calendar.cancelled') }}</span>
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('calendar.back') }}</a>
        <a href="{{ route('calendar.index', ['view' => 'day', 'date' => $event->start_date->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-day me-1"></i>{{ mawa_e('calendar.view_day') }}</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <h6 class="card-title mb-3">{{ mawa_e('calendar.event_details') }}</h6>
            <table class="table table-sm align-middle">
                <tr><td class="text-muted" style="width:160px">{{ mawa_e('calendar.type') }}</td><td>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</td></tr>
                <tr>
                    <td class="text-muted">{{ mawa_e('calendar.date') }}</td>
                    <td>
                        {{ $event->start_date->format('l, j M Y') }}
                        @if(!$event->is_all_day && $event->start_time)
                            — {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}
                            @if($event->end_time)
                                to {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
                            @endif
                        @endif
                    </td>
                </tr>
                @if ($event->is_all_day)
                    <tr><td class="text-muted">{{ mawa_e('calendar.duration') }}</td><td>{{ mawa_e('calendar.all_day') }}</td></tr>
                @endif
                @if ($event->description)
                    <tr><td class="text-muted">{{ mawa_e('calendar.description_label') }}</td><td>{!! nl2br(e($event->description)) !!}</td></tr>
                @endif
                @if ($event->branch)
                    <tr><td class="text-muted">{{ mawa_e('calendar.branch') }}</td><td>{{ $event->branch->name }}</td></tr>
                @endif
                @if ($event->course)
                    <tr><td class="text-muted">{{ mawa_e('calendar.course') }}</td><td>{{ $event->course->name }}</td></tr>
                @endif
                @if ($event->subject)
                    <tr><td class="text-muted">{{ mawa_e('calendar.subject') }}</td><td>{{ $event->subject->name }}</td></tr>
                @endif
                @if ($event->batch)
                    <tr><td class="text-muted">{{ mawa_e('calendar.batch') }}</td><td>{{ $event->batch->name }}</td></tr>
                @endif
                @if ($event->teacher)
                    <tr><td class="text-muted">{{ mawa_e('calendar.teacher') }}</td><td>{{ $event->teacher->first_name }} {{ $event->teacher->last_name }}</td></tr>
                @endif
                @if ($event->room)
                    <tr><td class="text-muted">{{ mawa_e('calendar.room') }}</td><td>{{ $event->room->name }}</td></tr>
                @endif
                @if ($event->academicYear)
                    <tr><td class="text-muted">{{ mawa_e('calendar.academic_year') }}</td><td>{{ $event->academicYear->name }}</td></tr>
                @endif
                @if ($event->parentEvent)
                    <tr><td class="text-muted">{{ mawa_e('calendar.recurring_from') }}</td><td><a href="{{ route('calendar.events.show', $event->parentEvent) }}">{{ $event->parentEvent->title }}</a> ({{ $event->parentEvent->start_date->format('j M Y') }})</td></tr>
                @endif
                @if ($event->childEvents->count() > 0)
                    <tr><td class="text-muted">{{ mawa_e('calendar.recurring_occurrences') }}</td><td>{{ $event->childEvents->count() }} scheduled</td></tr>
                @endif
                <tr><td class="text-muted">{{ mawa_e('calendar.created') }}</td><td>{{ $event->created_at->format('j M Y g:i A') }}</td></tr>
            </table>
        </div>

        @if ($event->recurrence_rule)
        <div class="admin-card">
            <h6 class="card-title mb-3">{{ mawa_e('calendar.recurrence_rule') }}</h6>
            <table class="table table-sm align-middle mb-0">
                <tr><td class="text-muted" style="width:160px">{{ mawa_e('calendar.frequency') }}</td><td>{{ ucfirst($event->recurrence_rule['frequency'] ?? '—') }}</td></tr>
                <tr><td class="text-muted">{{ mawa_e('calendar.interval') }}</td><td>{{ mawa_e('calendar.every') }} {{ $event->recurrence_rule['interval'] ?? 1 }} {{ $event->recurrence_rule['frequency'] ?? mawa_e('calendar.periods') }}</td></tr>
                @if (!empty($event->recurrence_rule['days_of_week']))
                    <tr><td class="text-muted">{{ mawa_e('calendar.days_label') }}</td><td>{{ implode(', ', array_map(fn($d) => ucfirst($d), $event->recurrence_rule['days_of_week'])) }}</td></tr>
                @endif
                @if (!empty($event->recurrence_rule['end_date']))
                    <tr><td class="text-muted">{{ mawa_e('calendar.until') }}</td><td>{{ \Carbon\Carbon::parse($event->recurrence_rule['end_date'])->format('j M Y') }}</td></tr>
                @endif
                @if (!empty($event->recurrence_rule['max_occurrences']))
                    <tr><td class="text-muted">{{ mawa_e('calendar.max_occurrences') }}</td><td>{{ $event->recurrence_rule['max_occurrences'] }}</td></tr>
                @endif
            </table>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        @if ($event->reminders->count() > 0)
        <div class="admin-card">
            <h6 class="card-title mb-3">{{ mawa_e('calendar.reminders') }}</h6>
            <ul class="list-unstyled mb-0">
                @foreach ($event->reminders as $r)
                    <li class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-bell text-muted"></i>
                        <span>{{ $r->minutes_before }} min before</span>
                        <span class="badge bg-{{ $r->is_sent ? 'success' : 'secondary' }}">{{ $r->is_sent ? mawa_e('calendar.sent') : mawa_e('calendar.pending') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="admin-card">
            <h6 class="card-title mb-3">{{ mawa_e('actions.actions') }}</h6>
            <div class="d-grid gap-2">
                <a href="{{ route('calendar.index', ['view' => 'day', 'date' => $event->start_date->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-calendar-day me-1"></i>{{ mawa_e('calendar.view_day_view') }}</a>
                @if ($event->batch)
                    <a href="{{ route('calendar.timetable', ['batch_id' => $event->batch_id, 'start_date' => $event->start_date->startOfWeek()->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-table me-1"></i>{{ mawa_e('calendar.batch_timetable') }}</a>
                @endif
                @if ($event->teacher)
                    <a href="{{ route('calendar.timetable', ['teacher_id' => $event->teacher_id, 'start_date' => $event->start_date->startOfWeek()->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-person me-1"></i>{{ mawa_e('calendar.teacher_timetable') }}</a>
                @endif
                @if ($event->room)
                    <a href="{{ route('calendar.timetable', ['room_id' => $event->room_id, 'start_date' => $event->start_date->startOfWeek()->format('Y-m-d')]) }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-door-open me-1"></i>{{ mawa_e('calendar.room_timetable') }}</a>
                @endif
                <hr class="my-1">
                <form action="{{ route('calendar.events.destroy', $event) }}" method="POST" onsubmit="return confirm('{{ mawa_e('calendar.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm text-start w-100"><i class="bi bi-trash me-1"></i>{{ mawa_e('calendar.delete_event') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
