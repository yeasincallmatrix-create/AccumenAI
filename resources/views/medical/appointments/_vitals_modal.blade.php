{{-- Record Vitals popup for the appointments list.
     Opened via openVitalsModal(row) from the React list (the whole row is
     passed, so freshly polled rows work too). Posts to the open quick
     endpoint — any authenticated medical user may record vitals; the
     doctor fence and audit trail still apply server-side. --}}
<div class="modal fade" id="vitalsQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('medical.vitals.quickStore') }}" method="POST" id="vitals-quick-form">
                @csrf
                <input type="hidden" name="appointment_id" id="vq_appointment_id" value="">
                <input type="hidden" name="patient_id" id="vq_patient_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-heart-pulse me-1"></i>Record Vitals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="vq_context">—</p>
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_temperature">Temp (°C)</label>
                            <input type="number" id="vq_temperature" name="temperature" step="0.1" min="35" max="42" class="form-control form-control-sm" placeholder="37.0">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_sys">BP Sys</label>
                            <input type="number" id="vq_sys" name="blood_pressure_systolic" min="60" max="250" class="form-control form-control-sm" placeholder="120">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_dia">BP Dia</label>
                            <input type="number" id="vq_dia" name="blood_pressure_diastolic" min="30" max="150" class="form-control form-control-sm" placeholder="80">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_pulse">Pulse</label>
                            <input type="number" id="vq_pulse" name="pulse" min="30" max="250" class="form-control form-control-sm" placeholder="72">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_hr">Heart Rate</label>
                            <input type="number" id="vq_hr" name="heart_rate" min="30" max="250" class="form-control form-control-sm" placeholder="72">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_rr">Resp. Rate</label>
                            <input type="number" id="vq_rr" name="respiratory_rate" min="5" max="60" class="form-control form-control-sm" placeholder="16">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_spo2">SpO2 %</label>
                            <input type="number" id="vq_spo2" name="spo2" min="70" max="100" class="form-control form-control-sm" placeholder="98">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_pain">Pain 0–10</label>
                            <input type="number" id="vq_pain" name="pain_score" min="0" max="10" class="form-control form-control-sm" placeholder="0">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_sugar">Blood Sugar</label>
                            <input type="number" id="vq_sugar" name="blood_sugar" step="0.1" min="20" max="500" class="form-control form-control-sm" placeholder="110">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_weight">Weight (kg)</label>
                            <input type="number" id="vq_weight" name="weight" step="0.1" min="1" max="300" class="form-control form-control-sm" placeholder="65">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="vq_height">Height</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="vq_height" name="height" step="0.1" min="30" max="250" class="form-control form-control-sm" placeholder="170">
                                <select id="vq_height_unit" class="form-select form-select-sm" style="max-width:4.5rem;flex:0 0 auto;" aria-label="Height unit">
                                    <option value="cm" selected>cm</option>
                                    <option value="ft">ft</option>
                                </select>
                            </div>
                            <div class="form-text" id="vq_height_hint"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="vq_notes">Notes</label>
                            <textarea id="vq_notes" name="notes" rows="2" class="form-control form-control-sm" placeholder="Optional note"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Vitals
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Height cm/ft toggle for the quick vitals modal: the server stores
// centimetres, so feet are converted back to cm on submit.
function bindHeightUnit(inputId, unitId, hintId) {
    var input = document.getElementById(inputId);
    var unit = document.getElementById(unitId);
    var hint = hintId ? document.getElementById(hintId) : null;
    if (!input || !unit) return;
    var FT = 30.48;
    function round(n, d) { var p = Math.pow(10, d); return (Math.round(n * p) / p).toString(); }
    function refreshHint() {
        if (!hint) return;
        var v = parseFloat(input.value);
        if (isNaN(v)) { hint.textContent = ''; return; }
        hint.textContent = unit.value === 'ft' ? '≈ ' + round(v * FT, 1) + ' cm' : '≈ ' + round(v / FT, 2) + ' ft';
    }
    function applyUnit() {
        var v = parseFloat(input.value);
        if (unit.value === 'ft') {
            if (!isNaN(v)) input.value = round(v / FT, 2);
            input.min = '1'; input.max = '9'; input.step = '0.01';
            input.placeholder = 'e.g. 5.58';
        } else {
            if (!isNaN(v)) input.value = round(v * FT, 1);
            input.min = '30'; input.max = '250'; input.step = '0.1';
            input.placeholder = 'e.g. 170';
        }
        refreshHint();
    }
    unit.addEventListener('change', applyUnit);
    input.addEventListener('input', refreshHint);
    var form = input.closest('form');
    if (form && !form.dataset.heightBound) {
        form.dataset.heightBound = '1';
        form.addEventListener('submit', function () {
            if (unit.value === 'ft') {
                var v = parseFloat(input.value);
                if (!isNaN(v)) input.value = round(v * FT, 1);
            }
        });
    }
    refreshHint();
}
bindHeightUnit('vq_height', 'vq_height_unit', 'vq_height_hint');
function openVitalsModal(row) {
    row = row || {};
    var form = document.getElementById('vitals-quick-form');
    form.reset();
    document.getElementById('vq_appointment_id').value = row.id || '';
    var bits = [];
    if (row.patient_name) bits.push(row.patient_name);
    if (row.serial_number) bits.push('Serial #' + row.serial_number);
    if (row.doctor_name) bits.push(row.doctor_name);
    document.getElementById('vq_context').textContent = bits.length ? bits.join(' · ') : '—';
    var modalEl = document.getElementById('vitalsQuickModal');
    if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
}
function openVitalsModalFrom(el) {
    // Open (and reset) first, then fill — openVitalsModal resets the form.
    openVitalsModal({
        id: el.getAttribute('data-vital-id'),
        patient_name: el.getAttribute('data-vital-patient'),
        serial_number: el.getAttribute('data-vital-serial')
    });
    var form = document.getElementById('vitals-quick-form');
    var payload = null;
    try { payload = JSON.parse(el.getAttribute('data-vitals-latest') || 'null'); } catch (e) { payload = null; }
    if (payload && typeof payload === 'object') {
        Object.keys(payload).forEach(function (k) {
            var input = form.querySelector('[name="' + k + '"]');
            if (input && payload[k] !== null && payload[k] !== undefined && payload[k] !== '') {
                input.value = payload[k];
            }
        });
    }
}
</script>
@endpush
