@extends('layouts.institute')

@section('title', 'New Treatment Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-list-check"></i> New Treatment Plan</h4>
        <a href="{{ route('medical.dental.plans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.dental.plans.store') }}">
        @csrf
        <div class="row g-3" x-data="treatmentPlan()">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plan Number</label>
                                <input type="text" class="form-control" value="{{ $planNumber }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $p)
                                        <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist *</label>
                                <select name="dentist_id" class="form-select" required>
                                    <option value="">Select Dentist</option>
                                    @foreach($dentists as $d)
                                        <option value="{{ $d->id }}" {{ old('dentist_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected End Date</label>
                                <input type="date" name="expected_end_date" class="form-control" value="{{ old('expected_end_date') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total Estimated Fee</label>
                                <input type="number" name="total_estimated_fee" class="form-control" value="{{ old('total_estimated_fee') }}" step="0.01" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Chief Complaint *</label>
                                <textarea name="chief_complaint" class="form-control" rows="2" required>{{ old('chief_complaint') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" rows="2">{{ old('diagnosis') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Treatment Summary</label>
                                <textarea name="treatment_summary" class="form-control" rows="2">{{ old('treatment_summary') }}</textarea>
                            </div>
                        </div>

                        {{-- Planned Steps --}}
                        <hr>
                        <h6>Planned Steps</h6>
                        <template x-for="(step, index) in steps" :key="index">
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-md-5">
                                    <input type="text" :name="'planned_steps['+index+'][procedure]'" class="form-control form-control-sm" placeholder="Procedure name" x-model="step.procedure" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" :name="'planned_steps['+index+'][tooth]'" class="form-control form-control-sm" placeholder="Tooth (optional)" x-model="step.tooth">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" :name="'planned_steps['+index+'][estimated_fee]'" class="form-control form-control-sm" placeholder="Fee" x-model="step.estimated_fee" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm" @click="removeStep(index)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="btn btn-outline-primary btn-sm" @click="addStep()"><i class="bi bi-plus"></i> Add Step</button>

                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Create Plan</button>
            </div>
        </div>
    </form>
</div>

<script>
function treatmentPlan() {
    return {
        steps: [{ procedure: '', tooth: '', estimated_fee: '' }],
        addStep() {
            this.steps.push({ procedure: '', tooth: '', estimated_fee: '' });
        },
        removeStep(index) {
            this.steps.splice(index, 1);
        }
    };
}
</script>
@endsection
