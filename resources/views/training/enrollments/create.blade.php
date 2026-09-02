@extends('layouts.institute')

@section('title', 'New Enrollment — AccumenAI')

@section('content')
<div class="page-header">
    <h4>New Enrollment</h4>
    <a href="{{ route('training.enrollments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.enrollments.index') }}" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Enrollments / Create</li>
    </ol>
</nav>
<div class="admin-card">
    <form method="POST" action="{{ route('training.enrollments.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Batch <span class="text-danger">*</span></label>
                <select name="batch_id" id="enrollBatch" class="form-select form-select-sm" required>
                    <option value="">— Select batch —</option>
                    @foreach($batches as $batch)
                        <option value="{{ $batch->id }}" data-remaining="{{ $batch->remaining ?? '∞' }}" data-enrolled="{{ $batch->enrolled_count ?? 0 }}" @selected(old('batch_id')==$batch->id || request('batch_id')==$batch->id)>{{ $batch->name }} ({{ $batch->seat_capacity ?? $batch->capacity ?? '∞' }} seats)</option>
                    @endforeach
                </select>
                <div id="batchCapacityInfo" class="form-text small mt-1"></div>
                @error('batch_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Trainee <span class="text-danger">*</span></label>
                <select name="trainee_id" class="form-select form-select-sm" required>
                    <option value="">Select Trainee</option>
                    @forelse($trainees as $trainee)
                        <option value="{{ $trainee->id }}" @selected(old('trainee_id')==$trainee->id)>{{ trim(($trainee->first_name ?? '').' '.($trainee->last_name ?? '')) ?: ($trainee->name ?? 'Trainee #'.$trainee->id) }} ({{ $trainee->reg_no }})</option>
                    @empty
                        <option value="" disabled>No active trainees found. Please create a student or check status.</option>
                    @endforelse
                </select>
                @error('trainee_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="roll_no">Roll Number <span class="text-danger">*</span></label>
                <input type="number" name="roll_no" id="roll_no" class="form-control form-control-sm" min="1" required placeholder="Enter unique roll number for this batch" value="{{ old('roll_no') }}">
                @error('roll_no')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Enroll</button>
        </div>
    </form>
</div>
@push('scripts')
<script>
document.getElementById('enrollBatch')?.addEventListener('change', function(){
    var opt = this.options[this.selectedIndex];
    var info = document.getElementById('batchCapacityInfo');
    if(!opt || !opt.value){ info.textContent=''; return; }
    var rem = opt.getAttribute('data-remaining');
    var enr = opt.getAttribute('data-enrolled');
    info.textContent = 'Enrolled: '+enr+' • Remaining: '+rem;
    info.className = 'form-text small mt-1 '+(rem=='0' ? 'text-danger' : 'text-success');
});
document.getElementById('enrollBatch')?.dispatchEvent(new Event('change'));
</script>
@endpush
@endsection
