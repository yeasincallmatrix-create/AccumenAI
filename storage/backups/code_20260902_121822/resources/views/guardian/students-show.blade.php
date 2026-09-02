@extends('guardian.layout')

@section('title', mawa_e('guardian.profile_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h1 class="h4 mb-0">{{ $student->full_name }}</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.attendance', $student->id) }}"><i class="bi bi-calendar-check me-1"></i>{{ mawa_e('guardian.attendance') }}</a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.results', $student->id) }}"><i class="bi bi-award me-1"></i>{{ mawa_e('guardian.results') }}</a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.fees', $student->id) }}"><i class="bi bi-cash-coin me-1"></i>{{ mawa_e('guardian.fees') }}</a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.certificates', $student->id) }}"><i class="bi bi-patch-check me-1"></i>{{ mawa_e('guardian.certificates') }}</a>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.documents', $student->id) }}"><i class="bi bi-folder2-open me-1"></i>{{ mawa_e('guardian.documents') }}</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-person-vcard me-1"></i>{{ mawa_e('guardian.student_details') }}
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-body-secondary" style="width:45%">{{ mawa_e('guardian.student_id') }}</th>
                            <td>{{ $student->student_id }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.roll_number') }}</th>
                            <td>{{ $enrollment?->roll_number ?? $student->roll_number ?? mawa_e('guardian.na') }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.full_name') }}</th>
                            <td>{{ $student->full_name }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.dob') }}</th>
                            <td>{{ $student->dob ? $student->dob->format('d M Y') : mawa_e('guardian.na') }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.phone') }}</th>
                            <td>{{ $student->phone ?? mawa_e('guardian.na') }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.email') }}</th>
                            <td>{{ $student->email ?? mawa_e('guardian.na') }}</td>
                        </tr>
                        <tr>
                            <th class="text-body-secondary">{{ mawa_e('guardian.status') }}</th>
                            <td><span class="badge text-bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ $student->status }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-mortarboard me-1"></i>{{ mawa_e('guardian.current_enrollment') }}
            </div>
            <div class="card-body">
                @if ($enrollment !== null)
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="text-body-secondary" style="width:45%">{{ mawa_e('guardian.course') }}</th>
                                <td>{{ $enrollment->course?->name ?? $enrollment->batch?->course?->name }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.batch') }}</th>
                                <td>{{ $enrollment->batch?->name }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.academic_year') }}</th>
                                <td>{{ $enrollment->batch?->academicYear?->name ?? mawa_e('guardian.na') }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.branch') }}</th>
                                <td>{{ $enrollment->batch?->branch?->name ?? mawa_e('guardian.na') }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.enrollment_date') }}</th>
                                <td>{{ $enrollment->enrollment_date ? \Illuminate\Support\Carbon::parse($enrollment->enrollment_date)->format('d M Y') : mawa_e('guardian.na') }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.fee_payable') }}</th>
                                <td>{{ number_format((float) $enrollment->fee_payable, 2) }}</td>
                            </tr>
                            <tr>
                                <th class="text-body-secondary">{{ mawa_e('guardian.status') }}</th>
                                <td><span class="badge text-bg-{{ $enrollment->status === 'active' ? 'success' : 'secondary' }}">{{ $enrollment->status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <p class="text-body-secondary small mb-0">{{ mawa_e('guardian.no_enrollment') }}</p>
                @endif
            </div>
        </div>

        @if ($enrollments->isNotEmpty() && $enrollments->count() > 1)
            <div class="card">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-clock-history me-1"></i>{{ mawa_e('guardian.enrollment_history') }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th>{{ mawa_e('guardian.course') }}</th>
                                    <th>{{ mawa_e('guardian.batch') }}</th>
                                    <th>{{ mawa_e('guardian.academic_year') }}</th>
                                    <th>{{ mawa_e('guardian.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($enrollments as $en)
                                    <tr>
                                        <td>{{ $en->course?->name ?? $en->batch?->course?->name }}</td>
                                        <td>{{ $en->batch?->name }}</td>
                                        <td>{{ $en->batch?->academicYear?->name ?? mawa_e('guardian.na') }}</td>
                                        <td><span class="badge text-bg-{{ $en->status === 'active' ? 'success' : 'secondary' }}">{{ $en->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection