@extends('layouts.institute')

@section('title', $exam->title . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'scheduled' => 'bg-secondary',
        'ongoing'   => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $statusNames = [
        'scheduled' => mawa_lang('exams.schedule'),
        'ongoing'   => mawa_lang('exams.ongoing'),
        'completed' => mawa_lang('exams.completed'),
        'cancelled' => mawa_lang('exams.cancelled'),
    ];
    $resultBadge = [
        'pass'   => 'text-success',
        'fail'   => 'text-danger',
        'absent' => 'text-muted',
    ];
    $full = rtrim(rtrim(number_format($exam->full_marks, 2), '0'), '.');
    $pass = rtrim(rtrim(number_format($exam->pass_marks, 2), '0'), '.');
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('exams.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('exams.title') }}
        </a>
        <h4 class="page-header-title">
            {{ $exam->title }}
            <span class="badge {{ $statusBadge[$exam->status] ?? 'bg-secondary' }} ms-1">{{ $statusNames[$exam->status] ?? $exam->status }}</span>
        </h4>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if ($exam->status !== 'cancelled')
            <button type="button" class="btn btn-outline-primary" data-exam-edit>
                <i class="bi bi-pencil me-1"></i>{{ mawa_e('exams.edit') }}
            </button>
        @endif
    </div>
</div>

<div class="row g-3 mt-1">

    <!-- Exam details card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-clipboard-check me-1"></i>{{ mawa_e('exams.heading') }}</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">{{ mawa_e('exams.batch') }}</dt>
                <dd class="col-7 fw-semibold">
                    @if ($exam->batch)
                        <a class="text-decoration-none" href="{{ route('batches.show', $exam->batch_id) }}">{{ $exam->batch->name }}</a>
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-5">{{ mawa_e('exams.course') }}</dt>
                <dd class="col-7">{{ $exam->course?->name ?? '—' }}</dd>
                <dt class="col-5">{{ mawa_e('exams.exam_date') }}</dt>
                <dd class="col-7">{{ $exam->exam_date ? \Illuminate\Support\Carbon::parse($exam->exam_date)->format('d M Y, h:i A') : '—' }}</dd>
                <dt class="col-5">{{ mawa_e('exams.full_marks') }}</dt>
                <dd class="col-7">{{ $full }}</dd>
                <dt class="col-5">{{ mawa_e('exams.pass_marks') }}</dt>
                <dd class="col-7">{{ $pass }}</dd>
                <dt class="col-5">{{ mawa_e('exams.status') }}</dt>
                <dd class="col-7">
                    <span class="badge {{ $statusBadge[$exam->status] ?? 'bg-secondary' }}">{{ $statusNames[$exam->status] ?? $exam->status }}</span>
                </dd>
            </dl>
        </div>
    </div>

    <!-- Weight distribution card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-pie-chart me-1"></i>{{ mawa_e('exams.percent_distribution') }}</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-7">{{ mawa_e('exams.written_percent') }}</dt>
                <dd class="col-5">{{ $exam->written_percent }}%</dd>
                <dt class="col-7">{{ mawa_e('exams.practical_percent') }}</dt>
                <dd class="col-5">{{ $exam->practical_percent }}%</dd>
                <dt class="col-7">{{ mawa_e('exams.viva_percent') }}</dt>
                <dd class="col-5">{{ $exam->viva_percent }}%</dd>
            </dl>
        </div>
    </div>

    <!-- Stats card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-1"></i>{{ mawa_e('exams.table_students') }}</h6>
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-primary">{{ $students->count() }}</div>
                        <div class="text-muted small">{{ mawa_e('exams.table_students') }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-success">{{ $passCount }}</div>
                        <div class="text-muted small">{{ mawa_lang('status.pass') }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-danger">{{ $failCount }}</div>
                        <div class="text-muted small">{{ mawa_lang('status.fail') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Marks entry -->
<div class="mt-4">
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h6 class="fw-bold text-primary mb-0">
                <i class="bi bi-pencil-square me-1"></i>{{ mawa_e('exams.marks_entry') }}
                <span class="badge bg-secondary ms-1">{{ $students->count() }}</span>
            </h6>
            @if ($exam->subjects->isNotEmpty())
                <div style="width:280px">
                    <label class="form-label small mb-1">{{ mawa_e('exams.select_subjects') }}</label>
                    <select id="marksSubjectSelect" class="form-select form-select-sm">
                        @foreach ($exam->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->subject?->name ?? '—' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <form id="marksForm" method="POST" action="{{ route('exams.marks', $exam) }}" data-ajax-enabled>
            @csrf

            @if ($exam->subjects->isNotEmpty())
                {{-- Per-subject marks entry (Practical / Viva) --}}
                @php
                    $examSubjects = $exam->subjects->loadMissing('subject:id,name');
                @endphp
                @foreach ($examSubjects as $subject)
                    @php
                        $hasComponents = $subject->components && $subject->components->isNotEmpty();
                        if ($hasComponents) {
                            $componentList = $subject->components;
                            $tMaxN = $componentList->sum('max_marks');
                            $tMax = rtrim(rtrim(number_format((float)$tMaxN, 2), '0'), '.');
                        } else {
                            $pMax = rtrim(rtrim(number_format((float) $subject->practical_marks, 2), '0'), '.');
                            $vMax = rtrim(rtrim(number_format((float) $subject->viva_marks, 2), '0'), '.');
                            $tMaxN = (float) $subject->practical_marks + (float) $subject->viva_marks;
                            $tMax = rtrim(rtrim(number_format($tMaxN, 2), '0'), '.');
                        }
                        $passVal = $subject->pass_marks !== null
                            ? rtrim(rtrim(number_format((float) $subject->pass_marks, 2), '0'), '.')
                            : '';
                        if (!$hasComponents) {
                            $pDead = (float) $subject->practical_marks <= 0;
                            $vDead = (float) $subject->viva_marks <= 0;
                            $deadStyle = 'background-color:#e9ecef;color:#adb5bd;cursor:not-allowed;';
                        }
                    @endphp
                    <div class="border rounded p-3 mb-4 marks-subject-block" id="marksSubjectBlock-{{ $subject->id }}" @if (!$loop->first) style="display:none" @endif>
                        <h6 class="fw-semibold mb-3">
                            {{ $subject->subject?->name ?? '—' }}
                        </h6>
                        <div class="row g-2 mb-3 align-items-center">
                            @if($hasComponents)
                                @foreach($componentList as $comp)
                                    @php $cMax = rtrim(rtrim(number_format((float)$comp->max_marks,2),'0'),'.'); @endphp
                                    <div class="col-auto">
                                        <span class="badge bg-light text-dark border">{{ $comp->component_name }}: {{ $cMax }}</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-auto">
                                    <span class="badge bg-light text-dark border">{{ mawa_e('exams.practical') }}: {{ $pMax }}</span>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-light text-dark border">{{ mawa_e('exams.viva') }}: {{ $vMax }}</span>
                                </div>
                            @endif
                            <div class="col-auto">
                                <label class="form-label small mb-0 me-1 fw-semibold">{{ mawa_e('exams.pass_marks') }}</label>
                                <input type="number" step="0.01" min="0" max="{{ $tMax }}"
                                       name="pass_marks[{{ $subject->id }}]"
                                       class="form-control form-control-sm d-inline-block marks-pass" style="width:110px"
                                       value="{{ $passVal }}">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ mawa_e('exams.roll') }}</th>
                                        <th>{{ mawa_e('exams.student') }}</th>
                                        <th>{{ mawa_e('exams.student_id') }}</th>
                                        @if($hasComponents)
                                            @foreach($componentList as $comp)
                                                @php $cMax = rtrim(rtrim(number_format((float)$comp->max_marks,2),'0'),'.'); @endphp
                                                <th style="min-width:110px">{{ $comp->component_name }}<br><small class="text-muted">max {{ $cMax }}</small></th>
                                            @endforeach
                                        @else
                                            <th style="min-width:110px">{{ mawa_e('exams.practical') }}</th>
                                            <th style="min-width:110px">{{ mawa_e('exams.viva') }}</th>
                                        @endif
                                        <th style="min-width:110px">{{ mawa_e('exams.total_marks') }}</th>
                                        <th>{{ mawa_e('exams.result') }}</th>
                                        <th style="min-width:140px">{{ mawa_e('exams.remarks') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($students as $enrollment)
                                        @php
                                            $key = $enrollment->student_id.'-'.$subject->id;
                                            $result = $results->get($key);
                                            $componentMarks = null;
                                            if ($result && $result->component_marks) {
                                                $componentMarks = is_array($result->component_marks) ? $result->component_marks : json_decode($result->component_marks, true);
                                            }
                                        @endphp
                                        <tr>
                                            <td class="text-muted">{{ $loop->iteration }}</td>
                                            <td class="fw-semibold">{{ $enrollment->roll_no ?: '—' }}</td>
                                            <td>
                                                <a class="fw-semibold text-decoration-none" href="{{ route('students.show', $enrollment->student_id) }}">
                                                    {{ $enrollment->student->full_name }}
                                                </a>
                                            </td>
                                            <td>{{ $enrollment->student->student_id ?? '—' }}</td>
                                            @if($hasComponents)
                                                @foreach($componentList as $comp)
                                                    @php
                                                        $cMax = rtrim(rtrim(number_format((float)$comp->max_marks,2),'0'),'.');
                                                        $val = null;
                                                        if ($componentMarks !== null && isset($componentMarks[$comp->id])) $val = $componentMarks[$comp->id];
                                                        elseif ($componentMarks !== null && isset($componentMarks[(string)$comp->id])) $val = $componentMarks[(string)$comp->id];
                                                        else {
                                                            // Fallback to legacy columns for migrated data
                                                            $lname = strtolower($comp->component_name);
                                                            if ($lname === 'practical') $val = $result?->practical_marks;
                                                            elseif ($lname === 'viva') $val = $result?->viva_marks;
                                                            elseif ($lname === 'written') $val = $result?->written_marks;
                                                        }
                                                    @endphp
                                                    <td>
                                                        <input type="number" step="0.01" min="0" max="{{ $cMax }}"
                                                               name="component_marks[{{ $subject->id }}][{{ $comp->id }}][{{ $enrollment->student_id }}]"
                                                               class="form-control form-control-sm marks-c" data-subject="{{ $subject->id }}"
                                                               value="{{ $val !== null ? rtrim(rtrim(number_format($val,2),'0'),'.') : '' }}"
                                                               placeholder="{{ $cMax }}">
                                                    </td>
                                                @endforeach
                                            @else
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="{{ $pMax }}"
                                                           name="practical[{{ $subject->id }}][{{ $enrollment->student_id }}]"
                                                           class="form-control form-control-sm marks-c" data-subject="{{ $subject->id }}"
                                                           value="{{ $result?->practical_marks !== null ? rtrim(rtrim(number_format($result->practical_marks, 2), '0'), '.') : '' }}"
                                                           placeholder="{{ $pMax }}"
                                                           @if ($pDead) readonly disabled style="{{ $deadStyle }}" @endif>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="{{ $vMax }}"
                                                           name="viva[{{ $subject->id }}][{{ $enrollment->student_id }}]"
                                                           class="form-control form-control-sm marks-c" data-subject="{{ $subject->id }}"
                                                           value="{{ $result?->viva_marks !== null ? rtrim(rtrim(number_format($result->viva_marks, 2), '0'), '.') : '' }}"
                                                           placeholder="{{ $vMax }}"
                                                           @if ($vDead) readonly disabled style="{{ $deadStyle }}" @endif>
                                                </td>
                                            @endif
                                            <td>
                                                <input type="text" readonly class="form-control form-control-sm bg-light marks-total"
                                                       value="{{ $result ? rtrim(rtrim(number_format($result->marks_obtained, 2), '0'), '.') : '' }}">
                                            </td>
                                            <td>
                                                <span class="fw-semibold marks-result {{ $result && isset($resultBadge[$result->result_status]) ? $resultBadge[$result->result_status] : 'text-muted' }}">{{ $result ? ucfirst($result->result_status) : '—' }}</span>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[{{ $subject->id }}][{{ $enrollment->student_id }}]"
                                                       class="form-control form-control-sm" maxlength="255"
                                                       value="{{ $result?->remarks ?? '' }}">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">{{ mawa_e('exams.no_students') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Legacy single-marks exam (no subjects) --}}
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ mawa_e('exams.roll') }}</th>
                                <th>{{ mawa_e('exams.student') }}</th>
                                <th>{{ mawa_e('exams.student_id') }}</th>
                                <th style="min-width:140px">{{ mawa_e('exams.marks_obtained') }}</th>
                                <th>{{ mawa_e('exams.result') }}</th>
                                <th>{{ mawa_e('exams.remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $enrollment)
                                @php $result = $results->get($enrollment->student_id.'-'); @endphp
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $enrollment->roll_no ?: '—' }}</td>
                                    <td>
                                        <a class="fw-semibold text-decoration-none" href="{{ route('students.show', $enrollment->student_id) }}">
                                            {{ $enrollment->student->full_name }}
                                        </a>
                                    </td>
                                    <td>{{ $enrollment->student->student_id ?? '—' }}</td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="{{ $full }}"
                                               name="marks[{{ $enrollment->student_id }}]"
                                               class="form-control form-control-sm"
                                               value="{{ $result?->marks_obtained !== null ? rtrim(rtrim(number_format($result->marks_obtained, 2), '0'), '.') : '' }}"
                                               placeholder="{{ mawa_e('exams.enter_marks') }}">
                                    </td>
                                    <td>
                                        @if ($result)
                                            <span class="fw-semibold {{ $resultBadge[$result->result_status] ?? 'text-muted' }}">{{ ucfirst($result->result_status) }}</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[{{ $enrollment->student_id }}]"
                                               class="form-control form-control-sm" maxlength="255"
                                               value="{{ $result?->remarks ?? '' }}">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">{{ mawa_e('exams.no_students') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($students->isNotEmpty())
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ mawa_e('exams.save_marks') }}</button>
                </div>
            @endif
        </form>
    </div>
</div>

@if ($exam->status !== 'cancelled')
    {{-- Edit Exam modal --}}
    <div class="modal fade" id="examModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <form class="modal-content" method="POST" action="{{ route('exams.update', $exam) }}" id="examForm" data-ajax-enabled>
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title">{{ mawa_e('exams.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="x_title">{{ mawa_e('exams.title_field') }} *</label>
                            <input type="text" id="x_title" name="title" class="form-control" maxlength="150"
                                   value="{{ $exam->title }}" placeholder="{{ mawa_e('exams.title_placeholder') }}" required>
                            @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="x_exam_date">{{ mawa_e('exams.exam_date') }} *</label>
                            <input type="datetime-local" id="x_exam_date" name="exam_date" class="form-control"
                                   value="{{ $exam->exam_date ? \Illuminate\Support\Carbon::parse($exam->exam_date)->format('Y-m-d\TH:i') : '' }}" required>
                            @error('exam_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="x_status">{{ mawa_e('exams.status') }}</label>
                            <select id="x_status" name="status" class="form-select">
                                @foreach ($statusNames as $slug => $label)
                                    <option value="{{ $slug }}" @selected($exam->status === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="x_full_marks">{{ mawa_e('exams.full_marks') }} *</label>
                            <input type="number" id="x_full_marks" name="full_marks" class="form-control" step="0.01" min="1" max="10000"
                                   value="{{ rtrim(rtrim(number_format($exam->full_marks, 2), '0'), '.') }}" required>
                            @error('full_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="x_pass_marks">{{ mawa_e('exams.pass_marks') }} *</label>
                            <input type="number" id="x_pass_marks" name="pass_marks" class="form-control" step="0.01" min="0"
                                   value="{{ rtrim(rtrim(number_format($exam->pass_marks, 2), '0'), '.') }}" required>
                            @error('pass_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-1"></i>Subject-wise Marks Distribution</h6>
                    <p class="text-muted small">Define assessment components per subject (e.g. Written, Practical, Viva, Quiz). Max marks per component will be summed for subject total.</p>
                    @foreach($exam->subjects as $examSubject)
                        <div class="card mb-3 exam-subject-card" data-subject-id="{{ $examSubject->id }}">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                <strong>{{ $examSubject->subject?->name ?? 'Subject #'.$examSubject->subject_id }} <small class="text-muted">#{{ $examSubject->id }}</small></strong>
                                <button type="button" class="btn btn-sm btn-outline-primary add-component-btn" data-subject="{{ $examSubject->id }}"><i class="bi bi-plus-lg me-1"></i>Add Component</button>
                            </div>
                            <div class="card-body p-2">
                                <div class="components-list" id="components-{{ $examSubject->id }}">
                                    @foreach($examSubject->components as $comp)
                                        <div class="row g-2 align-items-center mb-2 component-row" data-component-id="{{ $comp->id }}">
                                            <input type="hidden" name="components[{{ $examSubject->id }}][{{ $loop->index }}][id]" value="{{ $comp->id }}">
                                            <div class="col-md-5">
                                                <input type="text" name="components[{{ $examSubject->id }}][{{ $loop->index }}][component_name]" class="form-control form-control-sm" placeholder="e.g. Written, Practical, Viva" value="{{ $comp->component_name }}" required maxlength="80">
                                            </div>
                                            <div class="col-md-3">
                                                <input type="number" step="0.01" min="0" max="10000" name="components[{{ $examSubject->id }}][{{ $loop->index }}][max_marks]" class="form-control form-control-sm" placeholder="Max marks" value="{{ rtrim(rtrim(number_format($comp->max_marks,2),'0'),'.') }}" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" step="0.01" min="0" max="100" name="components[{{ $examSubject->id }}][{{ $loop->index }}][weight]" class="form-control form-control-sm" placeholder="Weight %" value="{{ $comp->weight !== null ? rtrim(rtrim(number_format($comp->weight,2),'0'),'.') : '' }}">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-component-btn"><i class="bi bi-trash"></i> Remove</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($examSubject->components->isEmpty())
                                    <div class="text-muted small text-center py-2 components-empty" id="empty-{{ $examSubject->id }}">No components yet — click Add Component to define.</div>
                                @endif
                                <template id="component-template-{{ $examSubject->id }}">
                                    <div class="row g-2 align-items-center mb-2 component-row">
                                        <div class="col-md-5">
                                            <input type="text" name="__NAME__[component_name]" class="form-control form-control-sm" placeholder="e.g. Midterm, Quiz, Final" required maxlength="80">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" step="0.01" min="0" max="10000" name="__NAME__[max_marks]" class="form-control form-control-sm" placeholder="Max marks" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" step="0.01" min="0" max="100" name="__NAME__[weight]" class="form-control form-control-sm" placeholder="Weight %">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-component-btn"><i class="bi bi-trash"></i> Remove</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endforeach
                    @if($exam->subjects->isEmpty())
                        <div class="alert alert-info small">No subjects linked to this exam. Add subjects via batch configuration.</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('examModal');
    if (modalEl) {
        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', '[data-exam-edit]', function (e, btn) {
                var currentModal = document.getElementById('examModal');
                if (currentModal) { bootstrap.Modal.getOrCreateInstance(currentModal).show(); }
            }, 'mtx-exam-edit');
        }
        var form = document.getElementById('examForm');
        form.addEventListener('submit', function (e) {
            if (!form.hasAttribute('data-ajax-enabled')) { return; }
        });
        // Dynamic component add/remove in exam modal
        document.querySelectorAll('.add-component-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var subjectId = btn.getAttribute('data-subject');
                var list = document.getElementById('components-'+subjectId);
                var tmpl = document.getElementById('component-template-'+subjectId);
                var idx = list.querySelectorAll('.component-row').length;
                var html = tmpl.innerHTML.replace(/__NAME__/g, 'components['+subjectId+']['+idx+']');
                var temp = document.createElement('div');
                temp.innerHTML = html;
                var row = temp.firstElementChild;
                if(row) list.appendChild(row);
                var empty = document.getElementById('empty-'+subjectId);
                if(empty) empty.style.display='none';
            });
        });
        document.addEventListener('click', function(e){
            var btn = e.target.closest('.remove-component-btn');
            if(!btn) return;
            var row = btn.closest('.component-row');
            if(row) row.remove();
            var card = btn.closest('.exam-subject-card');
            if(card){
                var sid = card.getAttribute('data-subject-id');
                var list = document.getElementById('components-'+sid);
                if(list && list.querySelectorAll('.component-row').length===0){
                    var empty = document.getElementById('empty-'+sid);
                    if(empty) empty.style.display='';
                }
            }
        });
    }

    function clearMarksErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
    }

    function showMarksErrors(form, errors) {
        clearMarksErrors(form);
        Object.keys(errors || {}).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (!field) {
                // Map "written.3.5" -> written[3][5], "marks.7" -> marks[7]
                var sub = key.match(/^(written|practical|viva|attendance|remarks|other)\.(\d+)\.(\d+)$/);
                if (sub) { field = form.querySelector('[name="' + sub[1] + '[' + sub[2] + '][' + sub[3] + ']"]'); }
                else {
                    var pm = key.match(/^pass_marks\.(\d+)$/);
                    if (pm) { field = form.querySelector('[name="pass_marks[' + pm[1] + ']"]'); }
                    else {
                        var leg = key.match(/^marks\.(\d+)$/);
                        if (leg) { field = form.querySelector('[name="marks[' + leg[1] + ']"]'); }
                    }
                }
            }
            if (field) {
                field.classList.add('is-invalid');
                var msg = document.createElement('div');
                msg.className = 'text-danger small mt-1';
                msg.textContent = (errors[key] || []).join(', ');
                field.parentNode.appendChild(msg);
            }
        });
    }

    function updateRowResult(tr) {
        if (!tr) { return; }
        var block = tr.closest('.marks-subject-block');
        var passInput = block ? block.querySelector('.marks-pass') : null;
        var resultEl = tr.querySelector('.marks-result');
        var pass = passInput && passInput.value !== '' ? parseFloat(passInput.value) : NaN;
        var total = 0;
        tr.querySelectorAll('.marks-c').forEach(function (i) { total += parseFloat(i.value) || 0; });
        var totalBox = tr.querySelector('.marks-total');
        if (totalBox) { totalBox.value = total ? String(Math.round(total * 100) / 100) : ''; }
        if (resultEl) {
            if (isNaN(pass)) {
                resultEl.textContent = '—';
                resultEl.className = 'fw-semibold marks-result text-muted';
            } else if (total && total >= pass) {
                resultEl.textContent = 'Pass';
                resultEl.className = 'fw-semibold marks-result text-success';
            } else if (total > 0) {
                resultEl.textContent = 'Fail';
                resultEl.className = 'fw-semibold marks-result text-danger';
            } else {
                resultEl.textContent = '—';
                resultEl.className = 'fw-semibold marks-result text-muted';
            }
        }
    }

    function bindSubjectTotals() {
        document.querySelectorAll('.marks-c').forEach(function (input) {
            if (input.dataset.totalBound) { return; }
            input.dataset.totalBound = '1';
            input.addEventListener('input', function () {
                updateRowResult(input.closest('tr'));
            });
        });
        document.querySelectorAll('.marks-pass').forEach(function (pass) {
            if (pass.dataset.passBound) { return; }
            pass.dataset.passBound = '1';
            pass.addEventListener('input', function () {
                var block = pass.closest('.marks-subject-block');
                if (!block) { return; }
                block.querySelectorAll('tbody tr').forEach(function (tr) { updateRowResult(tr); });
            });
        });
    }

    var subjectSelect = document.getElementById('marksSubjectSelect');
    if (subjectSelect) {
        var blocks = Array.prototype.slice.call(document.querySelectorAll('.marks-subject-block'));
        subjectSelect.addEventListener('change', function () {
            var id = subjectSelect.value;
            blocks.forEach(function (block) {
                block.style.display = block.id === 'marksSubjectBlock-' + id ? '' : 'none';
            });
        });
    }

    var marksForm = document.getElementById('marksForm');
    if (marksForm && window.Monetix && Monetix.request) {
        bindSubjectTotals();
        marksForm.addEventListener('submit', function (e) {
            if (!marksForm.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            clearMarksErrors(marksForm);
            var btn = marksForm.querySelector('[type="submit"]');
            var restore = Monetix.loading(btn);
            Monetix.request(marksForm.action, { method: 'POST', body: new FormData(marksForm) })
                .then(function (res) {
                    if (restore) { restore(); }
                    if (res && res.errors) { showMarksErrors(marksForm, res.errors); return; }
                    if (res && res.success === false) {
                        if (Monetix.toast) { Monetix.toast(res.message || 'Could not save marks.', 'danger'); }
                        return;
                    }
                    if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                    if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname, { preserveFocus: false }); }
                })
                .catch(function () {
                    if (restore) { restore(); }
                    if (Monetix.toast) { Monetix.toast('Could not save marks. Please try again.', 'danger'); }
                });
        });
    }
})();
</script>
@endpush