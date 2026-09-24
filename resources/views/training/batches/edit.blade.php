@extends('layouts.institute')

@section('title', 'Edit Batch — Training — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.batches.index') }}" class="text-decoration-none">Batches</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<div class="page-header">
    <h4 class="page-header-title">Edit Batch — {{ $batch->name }}</h4>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('training.batches.update', $batch->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Batch Name *</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="255" value="{{ old('name', $batch->name) }}" required>
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="course_id">Course *</label>
                <select id="course_id" name="course_id" class="form-select" required>
                    <option value="">— Select course —</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('course_id', $batch->course_id) == $course->id)>
                            {{ $course->name }}@if($course->course_code) ({{ $course->course_code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('course_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control"
                       value="{{ old('start_date', optional($batch->start_date)->format('Y-m-d')) }}">
                @error('start_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control"
                       value="{{ old('end_date', optional($batch->end_date)->format('Y-m-d')) }}"
                       min="{{ old('start_date', optional($batch->start_date)->format('Y-m-d')) }}">
                @error('end_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="batch_code">Batch Code</label>
                <input type="text" id="batch_code" name="batch_code" class="form-control" maxlength="50" value="{{ old('batch_code', $batch->batch_code) }}" placeholder="e.g. B001">
                @error('batch_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="seat_capacity">Batch Capacity</label>
                <input type="number" id="seat_capacity" name="seat_capacity" class="form-control" min="1" max="10000"
                       value="{{ old('seat_capacity', $batch->seat_capacity) }}" placeholder="e.g. 30">
                <div class="form-text text-muted small">Maximum number of seats in this batch.</div>
                @error('seat_capacity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach (['upcoming', 'ongoing', 'completed', 'cancelled', 'archived'] as $s)
                        <option value="{{ $s }}" {{ old('status', $batch->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
            <a href="{{ route('training.batches.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
