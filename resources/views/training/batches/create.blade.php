@extends('layouts.institute')

@section('title', 'Create Batch — Training — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.batches.index') }}" class="text-decoration-none">Batches</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
</nav>

<div class="page-header">
    <h4 class="page-header-title">Create Batch</h4>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('training.batches.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Batch Name *</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="255" value="{{ old('name') }}" required>
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="course_id">Course *</label>
                <select id="course_id" name="course_id" class="form-select" required>
                    <option value="">— Select course —</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                            {{ $course->name }}@if($course->course_code) ({{ $course->course_code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('course_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($courses->isEmpty())
                    <div class="form-text text-warning small">No courses found. Create a training course first.</div>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label" for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="{{ old('start_date') }}">
                @error('start_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="{{ old('end_date') }}" min="{{ old('start_date') }}">
                @error('end_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="batch_code">Batch Code</label>
                <input type="text" id="batch_code" name="batch_code" class="form-control" maxlength="50" value="{{ old('batch_code') }}" placeholder="e.g. B001">
                <div class="form-text text-muted small">Leave blank to auto-generate.</div>
                @error('batch_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="seat_capacity">Batch Capacity</label>
                <input type="number" id="seat_capacity" name="seat_capacity" class="form-control" min="1" max="10000"
                       value="{{ old('seat_capacity', 30) }}" placeholder="e.g. 30">
                <div class="form-text text-muted small">Maximum number of seats in this batch.</div>
                @error('seat_capacity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="upcoming" {{ old('status', 'upcoming') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                    <option value="ongoing" {{ old('status') === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                    <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="archived" {{ old('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            <a href="{{ route('training.batches.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
