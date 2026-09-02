@php
    $name = $row['student']->full_name ?: trim($row['student']->first_name.' '.$row['student']->last_name);
    $eligible = $row['eligibility']['eligible'];
    $outcome = $row['eligibility']['outcome'];
@endphp
<div class="d-flex align-items-center justify-content-between py-2 border-bottom">
    <div>
        <span class="fw-semibold">{{ $name }}</span>
        @if ($row['student']->reg_no)
            <span class="text-muted small">({{ $row['student']->reg_no }})</span>
        @endif
        <div class="text-muted small">{{ mawa_e('alumni.outcome') }}{{ $outcome }}</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @if ($eligible)
            <span class="badge text-bg-success">{{ mawa_e('alumni.badge_eligible') }}</span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-alumni
                    data-student-id="{{ $row['student']->id }}" data-student-name="{{ $name }}">
                <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('alumni.add_btn') }}
            </button>
        @else
            <span class="badge text-bg-secondary">{{ mawa_e('alumni.badge_not_eligible') }}</span>
        @endif
    </div>
</div>
