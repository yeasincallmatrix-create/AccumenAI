{{-- Collect Visit Fee popup for the Live Queue cards.
     Opened via openFeeModal(btn) when the doctor's fee timing requires
     collection at this step (pre-visit on Start, post-visit on Complete).
     The server recomputes the amount — display values are informational. --}}
<div class="modal fade" id="feeCollectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="fee-collect-form">
                @csrf
                <input type="hidden" name="action" id="fee_action" value="">
                <input type="hidden" name="redirect_to" id="fee_redirect_to" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-1"></i>Collect Visit Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-primary py-2 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle fs-4"></i>
                        <div>
                            <div class="text-muted small">Patient</div>
                            <div class="fw-bold fs-5" id="fee_patient">—</div>
                        </div>
                    </div>
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Fee Type</dt><dd class="col-sm-8" id="fee_type">—</dd>
                        <dt class="col-sm-4">Amount</dt><dd class="col-sm-8 fw-semibold" id="fee_amount">—</dd>
                        <dt class="col-sm-4">Next Step</dt><dd class="col-sm-8" id="fee_step">—</dd>
                    </dl>
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-sm-5">
                            <label class="form-label small mb-1" for="fee_discount_type">Discount</label>
                            <select class="form-select form-select-sm" name="discount_type" id="fee_discount_type" onchange="recalcFeePayable()">
                                <option value="none">No discount</option>
                                <option value="percent">Percent (%)</option>
                                <option value="flat" selected>Flat (৳)</option>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small mb-1" for="fee_discount_value">Discount value</label>
                            <input type="number" class="form-control form-control-sm" name="discount_value" id="fee_discount_value"
                                   min="0" step="0.01" placeholder="0" oninput="recalcFeePayable()">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-2">
                        <span class="text-muted small">Payable after discount</span>
                        <span class="fw-bold fs-5" id="fee_payable">—</span>
                    </div>
                    <p class="text-muted small mb-0" id="fee_note">Confirm collection to proceed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cash-coin me-1"></i>Collect &amp; Proceed
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
var feeBaseAmount = 0;
function feeDiscountAmount() {
    var typeEl = document.getElementById('fee_discount_type');
    var valEl = document.getElementById('fee_discount_value');
    var type = typeEl ? typeEl.value : 'none';
    var val = valEl ? parseFloat(valEl.value) : NaN;
    if (isNaN(val) || val <= 0 || feeBaseAmount <= 0) return 0;
    if (type === 'percent') return Math.min(feeBaseAmount * Math.min(val, 100) / 100, feeBaseAmount);
    if (type === 'flat') return Math.min(val, feeBaseAmount);
    return 0;
}
function recalcFeePayable() {
    var payable = Math.max(0, feeBaseAmount - feeDiscountAmount());
    var el = document.getElementById('fee_payable');
    if (el) el.textContent = '৳' + payable.toFixed(2);
}
function openFeeModal(btn) {
    var form = document.getElementById('fee-collect-form');
    form.action = btn.getAttribute('data-fee-url') || '';
    var action = btn.getAttribute('data-fee-action') || 'start';
    document.getElementById('fee_action').value = action;
    document.getElementById('fee_redirect_to').value = btn.getAttribute('data-fee-redirect') || '';
    document.getElementById('fee_patient').textContent = btn.getAttribute('data-fee-patient') || '—';
    document.getElementById('fee_type').textContent = btn.getAttribute('data-fee-type') || '—';
    var amount = btn.getAttribute('data-fee-amount') || '0';
    feeBaseAmount = parseFloat(amount) || 0;
    document.getElementById('fee_amount').textContent = '৳' + feeBaseAmount.toFixed(2);
    // Fresh discount on every open — never carry over the last patient's.
    // Flat is the default type; an empty value still means no discount.
    document.getElementById('fee_discount_type').value = 'flat';
    document.getElementById('fee_discount_value').value = '';
    recalcFeePayable();
    var isStart = action === 'start';
    document.getElementById('fee_step').textContent = isStart ? 'Record fee (stay Checked In)' : 'Complete visit (Completed)';
    document.getElementById('fee_note').textContent = isStart
        ? 'This doctor collects the fee before the visit. Confirm to record it — the Start Consultation button appears after.'
        : 'This doctor collects the fee after the visit. Confirm collection to complete the visit.';
    var modalEl = document.getElementById('feeCollectModal');
    if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
}
</script>
@endpush
