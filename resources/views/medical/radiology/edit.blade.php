@extends('layouts.institute')

@section('title', 'Edit Radiology Order — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit {{ $order->order_number }}</h4>
        <a href="{{ route('medical.radiology.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('medical.radiology.orders.update', $order) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <!-- Study Details -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-body-text"></i> Study Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Modality *</label>
                                <select name="modality" class="form-select" required>
                                    @foreach($modalities as $key => $label)
                                        <option value="{{ $key }}" @selected(old('modality', $order->modality) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Body Part *</label>
                                <select name="body_part" class="form-select" required>
                                    @foreach($bodyParts as $part)
                                        <option value="{{ $part }}" @selected(old('body_part', $order->body_part) === $part)>{{ $part }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Laterality</label>
                                <select name="laterality" class="form-select">
                                    <option value="">Not Applicable</option>
                                    <option value="left" @selected(old('laterality', $order->laterality) === 'left')>Left</option>
                                    <option value="right" @selected(old('laterality', $order->laterality) === 'right')>Right</option>
                                    <option value="bilateral" @selected(old('laterality', $order->laterality) === 'bilateral')>Bilateral</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fee</label>
                                <input type="number" name="fee" class="form-control" value="{{ old('fee', $order->fee) }}" min="0" step="0.01">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Clinical Indication</label>
                                <textarea name="clinical_indication" class="form-control" rows="3">{{ old('clinical_indication', $order->clinical_indication) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flags & Doctor -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-flag"></i> Flags & Doctor</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Referring Doctor</label>
                                <select name="doctor_id" class="form-select">
                                    <option value="">Select Doctor</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id', $order->doctor_id) == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_contrast" value="1" class="form-check-input" id="is_contrast" @checked(old('is_contrast', $order->is_contrast))>
                                    <label class="form-check-label" for="is_contrast">Contrast Required</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="contrast_type" class="form-control" value="{{ old('contrast_type', $order->contrast_type) }}" placeholder="Contrast type">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_urgent" value="1" class="form-check-input" id="is_urgent" @checked(old('is_urgent', $order->is_urgent))>
                                    <label class="form-check-label" for="is_urgent">Urgent</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_fasting_required" value="1" class="form-check-input" id="is_fasting_required" @checked(old('is_fasting_required', $order->is_fasting_required))>
                                    <label class="form-check-label" for="is_fasting_required">Fasting Required</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach(\App\Models\Medical\RadiologyOrder::STATUSES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $order->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Changes</button>
                <a href="{{ route('medical.radiology.orders.show', $order) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
