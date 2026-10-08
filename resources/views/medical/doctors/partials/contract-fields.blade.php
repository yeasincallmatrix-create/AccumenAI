@php
    // Works for both create (old input only) and edit ($doctor present).
    $employmentTypes = \App\Models\HrEmployee::EMPLOYMENT_TYPES;
    $contractEmploymentType = old('employment_type', $doctor->employment_type ?? null);
    $contractDesignationId = old('designation_id', $doctor->designation_id ?? null);
    $contractFeePercentage = old('doctor_fee_percentage', $doctor->doctor_fee_percentage ?? null);
    $contractAllowDiscount = (bool) old('allow_discount', $doctor->allow_discount ?? false);
    $contractMaxDiscount = old('max_discount_percent', $doctor->max_discount_percent ?? null);
@endphp

<div class="card mt-3" id="contract-card">
    <div class="card-header">
        <strong><i class="bi bi-file-earmark-text me-1"></i>Contract</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label" for="employment_type">Employment Type</label>
                    <select id="employment_type" name="employment_type" class="form-select @error('employment_type') is-invalid @enderror">
                        <option value="">Select...</option>
                        @foreach($employmentTypes as $type)
                            <option value="{{ $type }}" @selected($contractEmploymentType === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    @error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label" for="designation_id">Designation</label>
                    <select id="designation_id" name="designation_id" class="form-select @error('designation_id') is-invalid @enderror">
                        <option value="">Select designation...</option>
                        @foreach(($designations ?? []) as $designation)
                            <option value="{{ $designation->id }}" @selected((string) $contractDesignationId === (string) $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                    @error('designation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if(($designations ?? collect())->isEmpty())
                        <div class="form-text">No designations yet — add them under HR → Designations.</div>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label" for="doctor_fee_percentage">Doctor Fee %</label>
                    <input type="number" id="doctor_fee_percentage" name="doctor_fee_percentage" class="form-control @error('doctor_fee_percentage') is-invalid @enderror" value="{{ $contractFeePercentage }}" min="0" max="100" step="0.01" placeholder="e.g. 60">
                    @error('doctor_fee_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="doctorFeeShare">Share of each consultation fee the doctor receives.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label class="form-label">Discount</label>
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" id="allow_discount" name="allow_discount" value="1" class="form-check-input" {{ $contractAllowDiscount ? 'checked' : '' }}>
                        <label class="form-check-label" for="allow_discount" id="discountToggleLabel">Not allowed</label>
                    </div>
                    <input type="number" id="max_discount_percent" name="max_discount_percent" class="form-control @error('max_discount_percent') is-invalid @enderror" value="{{ $contractMaxDiscount }}" min="0" max="100" step="0.01" placeholder="Max discount %">
                    @error('max_discount_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="discountToggleHelp">Switch on to set how much discount this doctor may give.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var toggle = document.getElementById('allow_discount');
    var discountInput = document.getElementById('max_discount_percent');
    var toggleLabel = document.getElementById('discountToggleLabel');
    var toggleHelp = document.getElementById('discountToggleHelp');

    function syncDiscount() {
        if (!toggle || !discountInput) return;
        var on = toggle.checked;
        discountInput.disabled = !on;
        if (!on) discountInput.value = '';
        if (toggleLabel) toggleLabel.textContent = on ? 'Allowed' : 'Not allowed';
        if (toggleHelp) {
            toggleHelp.textContent = on
                ? 'This doctor may discount up to the % set above.'
                : 'Switch on to set how much discount this doctor may give.';
        }
    }

    if (toggle) {
        toggle.addEventListener('change', syncDiscount);
        syncDiscount();
    }

    var feeInput = document.getElementById('consultation_fee');
    var pctInput = document.getElementById('doctor_fee_percentage');
    var shareOut = document.getElementById('doctorFeeShare');

    function syncShare() {
        if (!feeInput || !pctInput || !shareOut) return;
        var fee = parseFloat(feeInput.value);
        var pct = parseFloat(pctInput.value);
        if (!isFinite(fee) || fee <= 0 || !isFinite(pct)) {
            shareOut.textContent = 'Share of each consultation fee the doctor receives.';
            return;
        }
        shareOut.textContent = 'Doctor receives ৳' + (fee * pct / 100).toFixed(2) + ' from each ৳' + fee.toFixed(2) + ' consultation.';
    }

    if (feeInput) feeInput.addEventListener('input', syncShare);
    if (pctInput) pctInput.addEventListener('input', syncShare);
    syncShare();
})();
</script>
