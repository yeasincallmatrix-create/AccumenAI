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
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-1"></i>Collect Visit Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Patient</dt><dd class="col-sm-8" id="fee_patient">—</dd>
                        <dt class="col-sm-4">Fee Type</dt><dd class="col-sm-8" id="fee_type">—</dd>
                        <dt class="col-sm-4">Amount</dt><dd class="col-sm-8 fw-semibold" id="fee_amount">—</dd>
                        <dt class="col-sm-4">Next Step</dt><dd class="col-sm-8" id="fee_step">—</dd>
                    </dl>
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
function openFeeModal(btn) {
    var form = document.getElementById('fee-collect-form');
    form.action = btn.getAttribute('data-fee-url') || '';
    var action = btn.getAttribute('data-fee-action') || 'start';
    document.getElementById('fee_action').value = action;
    document.getElementById('fee_patient').textContent = btn.getAttribute('data-fee-patient') || '—';
    document.getElementById('fee_type').textContent = btn.getAttribute('data-fee-type') || '—';
    var amount = btn.getAttribute('data-fee-amount') || '0';
    document.getElementById('fee_amount').textContent = '৳' + amount;
    var isStart = action === 'start';
    document.getElementById('fee_step').textContent = isStart ? 'Start consultation (In Progress)' : 'Complete visit (Completed)';
    document.getElementById('fee_note').textContent = isStart
        ? 'This doctor collects the fee before the visit. Confirm collection to start the consultation.'
        : 'This doctor collects the fee after the visit. Confirm collection to complete the visit.';
    var modalEl = document.getElementById('feeCollectModal');
    if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
}
</script>
@endpush
