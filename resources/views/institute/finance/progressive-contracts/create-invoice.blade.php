@extends('layouts.standalone')

@section('title', 'Create Progress Invoice — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Create Progress Invoice</h4>
    <p>Issue a progress invoice against contract {{ $contract->contract_number }} — {{ $contract->title }}</p>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <form method="POST" action="{{ route('finance.progressive-contracts.store-invoice', $contract) }}" id="invoiceForm">
                @csrf

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Billing Method <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="billing_method" id="methodPercentage" value="percentage" @checked(old('billing_method', 'percentage') === 'percentage') required>
                                <label class="form-check-label" for="methodPercentage">Percentage of contract</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="billing_method" id="methodAmount" value="amount" @checked(old('billing_method') === 'amount')>
                                <label class="form-check-label" for="methodAmount">Fixed amount</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="billing_method" id="methodMilestone" value="milestone" @checked(old('billing_method') === 'milestone')>
                                <label class="form-check-label" for="methodMilestone">Milestone</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6" id="progressValueGroup">
                        <label class="form-label" id="progressValueLabel">% Complete <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="100" class="form-control form-control-sm" name="progress_value" id="progressValue" value="{{ old('progress_value') }}" required>
                        <small class="text-muted" id="progressValueHint">Percentage of total contract value to bill.</small>
                    </div>

                    <div class="col-md-6" id="milestoneNameGroup" style="display:none;">
                        <label class="form-label">Milestone Name</label>
                        <input type="text" class="form-control form-control-sm" name="milestone_name" value="{{ old('milestone_name') }}" maxlength="200" placeholder="e.g. Phase 1: Discovery">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="due_date" value="{{ old('due_date', now()->addDays(30)->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control form-control-sm" name="line_description" value="{{ old('line_description', $contract->title) }}" maxlength="500">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_final" id="isFinal" value="1" @checked(old('is_final'))>
                            <label class="form-check-label" for="isFinal">This is the final invoice (releases retention)</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Create progress invoice</button>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.progressive-contracts.show', $contract) }}">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <h6 class="card-title">Contract Summary</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted">Total Value</td><td class="text-end">{{ number_format((float) $contract->total_value, 2) }}</td></tr>
                    <tr><td class="text-muted">Billed to Date</td><td class="text-end">{{ number_format((float) $contract->total_billed, 2) }}</td></tr>
                    <tr><td class="text-muted">Remaining</td><td class="text-end">{{ number_format((float) $contract->remainingValue(), 2) }}</td></tr>
                    <tr><td class="text-muted">Retention %</td><td class="text-end">{{ number_format((float) $contract->retention_percentage, 2) }}%</td></tr>
                    <tr><td class="text-muted">Retention Held</td><td class="text-end">{{ number_format((float) $contract->retentionHeld(), 2) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-card">
            <h6 class="card-title">Invoice Preview</h6>
            <div id="previewBox">
                <p class="text-muted mb-0">Enter billing details to see preview.</p>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const totalValue = {{ (float) $contract->total_value }};
    const retentionPct = {{ (float) $contract->retention_percentage }};
    const billedToDate = {{ (float) $contract->total_billed }};

    const methodRadios = document.querySelectorAll('input[name="billing_method"]');
    const progressValue = document.getElementById('progressValue');
    const progressLabel = document.getElementById('progressValueLabel');
    const progressHint = document.getElementById('progressValueHint');
    const milestoneGroup = document.getElementById('milestoneNameGroup');
    const progressGroup = document.getElementById('progressValueGroup');
    const isFinal = document.getElementById('isFinal');
    const previewBox = document.getElementById('previewBox');

    function updateUI() {
        const method = document.querySelector('input[name="billing_method"]:checked').value;
        if (method === 'percentage') {
            progressLabel.textContent = '% Complete *';
            progressHint.textContent = 'Percentage of total contract value to bill.';
            progressValue.max = 100;
            progressValue.placeholder = 'e.g. 25';
            milestoneGroup.style.display = 'none';
            progressGroup.style.display = '';
        } else if (method === 'amount') {
            progressLabel.textContent = 'Amount *';
            progressHint.textContent = 'Fixed amount to bill.';
            progressValue.max = totalValue;
            progressValue.placeholder = 'e.g. 50000';
            milestoneGroup.style.display = 'none';
            progressGroup.style.display = '';
        } else {
            progressLabel.textContent = 'Amount *';
            progressHint.textContent = 'Milestone amount to bill.';
            progressValue.max = totalValue;
            progressValue.placeholder = 'e.g. 50000';
            milestoneGroup.style.display = '';
            progressGroup.style.display = '';
        }
        updatePreview();
    }

    function updatePreview() {
        const method = document.querySelector('input[name="billing_method"]:checked').value;
        const val = parseFloat(progressValue.value) || 0;
        let invoiceAmount = 0;
        if (method === 'percentage') {
            invoiceAmount = totalValue * (val / 100);
        } else {
            invoiceAmount = val;
        }
        invoiceAmount = Math.round(invoiceAmount * 100) / 100;
        const newCumulative = Math.round((billedToDate + invoiceAmount) * 100) / 100;
        const final = isFinal.checked;
        let retentionThis = 0;
        if (!final && retentionPct > 0) {
            retentionThis = Math.round(invoiceAmount * (retentionPct / 100) * 100) / 100;
        }
        const remaining = Math.round((totalValue - newCumulative) * 100) / 100;

        let html = '<table class="table table-sm mb-0">';
        html += '<tr><td class="text-muted">Invoice Amount</td><td class="text-end fw-semibold">' + invoiceAmount.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        if (retentionThis > 0) {
            html += '<tr><td class="text-muted">Retention Held</td><td class="text-end">' + retentionThis.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        }
        if (final) {
            html += '<tr><td class="text-muted">Retention Released</td><td class="text-end text-success">Final invoice</td></tr>';
        }
        html += '<tr><td class="text-muted">Cumulative Billed</td><td class="text-end">' + newCumulative.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        html += '<tr><td class="text-muted">Remaining</td><td class="text-end">' + remaining.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        html += '</table>';
        previewBox.innerHTML = html;
    }

    methodRadios.forEach(r => r.addEventListener('change', updateUI));
    progressValue.addEventListener('input', updatePreview);
    isFinal.addEventListener('change', updatePreview);
    updateUI();
})();
</script>
@endsection
