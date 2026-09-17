@extends('layouts.institute')

@section('title', 'Edit Treatment Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-list-check"></i> Edit Plan {{ $plan->plan_number }}</h4>
        <a href="{{ route('medical.dental.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.dental.plans.update', $plan) }}">
        @csrf
        @method('PUT')
        <div class="row g-3" x-data="treatmentPlan({{ json_encode($plan->planned_steps ?? []) }})">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    @foreach($patients as $p)
                                        <option value="{{ $p->id }}" {{ old('patient_id', $plan->patient_id) == $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist *</label>
                                <select name="dentist_id" class="form-select" required>
                                    @foreach($dentists as $d)
                                        <option value="{{ $d->id }}" {{ old('dentist_id', $plan->dentist_id) == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $k => $v)
                                        <option value="{{ $k }}" {{ old('status', $plan->status) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $plan->start_date->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected End Date</label>
                                <input type="date" name="expected_end_date" class="form-control" value="{{ old('expected_end_date', $plan->expected_end_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total Estimated Fee</label>
                                <input type="number" name="total_estimated_fee" class="form-control" value="{{ old('total_estimated_fee', $plan->total_estimated_fee) }}" step="0.01" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Chief Complaint *</label>
                                <textarea name="chief_complaint" class="form-control" rows="2" required>{{ old('chief_complaint', $plan->chief_complaint) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" rows="2">{{ old('diagnosis', $plan->diagnosis) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Treatment Summary</label>
                                <textarea name="treatment_summary" class="form-control" rows="2">{{ old('treatment_summary', $plan->treatment_summary) }}</textarea>
                            </div>
                        </div>

                        <hr>
                        <h6>Planned Steps</h6>
                        <template x-for="(step, index) in steps" :key="index">
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-md-4">
                                    <input type="text" :name="'planned_steps['+index+'][procedure]'" class="form-control form-control-sm" placeholder="Procedure" x-model="step.procedure" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" :name="'planned_steps['+index+'][tooth]'" class="form-control form-control-sm" placeholder="Tooth" x-model="step.tooth">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" :name="'planned_steps['+index+'][estimated_fee]'" class="form-control form-control-sm" placeholder="Fee" x-model="step.estimated_fee" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <select :name="'planned_steps['+index+'][status]'" class="form-select form-select-sm" x-model="step.status">
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm" @click="removeStep(index)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="btn btn-outline-primary btn-sm" @click="addStep()"><i class="bi bi-plus"></i> Add Step</button>

                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $plan->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Update Plan</button>
            </div>
        </div>
    </form>
</div>

<script>
function treatmentPlan(existingSteps) {
    return {
        steps: existingSteps.length > 0 ? existingSteps : [{ procedure: '', tooth: '', estimated_fee: '', status: 'pending' }],
        addStep() {
            this.steps.push({ procedure: '', tooth: '', estimated_fee: '', status: 'pending' });
        },
        removeStep(index) {
            this.steps.splice(index, 1);
        }
    };
}
</script>
@endsection
