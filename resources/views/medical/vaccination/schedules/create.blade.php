@extends('layouts.institute')

@section('title', 'New Vaccination Schedule — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> New Vaccination Schedule</h4>
        <a href="{{ route('medical.vaccination.schedules.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.vaccination.schedules.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $p)
                                        <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('patient_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vaccine *</label>
                                <select name="vaccine_master_id" class="form-select" required>
                                    <option value="">Select Vaccine</option>
                                    @foreach($vaccines as $v)
                                        <option value="{{ $v->id }}" {{ old('vaccine_master_id') == $v->id ? 'selected' : '' }}>{{ $v->name }} ({{ $v->doses_in_series }} doses)</option>
                                    @endforeach
                                </select>
                                @error('vaccine_master_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <p class="text-muted mb-2">Select a patient and vaccine to generate the full EPI schedule automatically, or fill a single dose manually.</p>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Create Schedule</button>
            </div>
        </div>
    </form>
</div>
@endsection
