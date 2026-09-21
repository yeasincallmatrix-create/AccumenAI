@extends('layouts.standalone')

@section('title', 'New Recurring Template — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>New Recurring Template</h4>
    <p>Create a template for automated recurring financial transactions.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.recurring-templates.store') }}" id="templateForm">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name') }}" required maxlength="200" placeholder="e.g. Monthly Office Rent">
            </div>
            <div class="col-md-6">
                <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mt-1">
                    @foreach (['journal_entry', 'invoice', 'vendor_bill', 'expense', 'payment'] as $type)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transaction_type" id="type_{{ $type }}" value="{{ $type }}" @checked(old('transaction_type', 'journal_entry') === $type) required>
                            <label class="form-check-label" for="type_{{ $type }}">{{ str_replace('_', ' ', ucfirst($type)) }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-12"><hr><h6>Schedule</h6></div>

            <div class="col-md-3">
                <label class="form-label">Frequency <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="frequency" id="frequency" required>
                    @foreach (['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'custom'] as $f)
                        <option value="{{ $f }}" @selected(old('frequency') === $f)>{{ ucfirst($f) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Every N</label>
                <input type="number" class="form-control form-control-sm" name="interval_count" value="{{ old('interval_count', 1) }}" min="1" max="365">
            </div>
            <div class="col-md-3" id="cronGroup" style="display:none;">
                <label class="form-label">Custom Cron <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="custom_cron" value="{{ old('custom_cron') }}" placeholder="0 9 * * 1">
                <small class="text-muted">e.g. 0 9 * * 1 (every Monday 9am)</small>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control form-control-sm" name="end_date" value="{{ old('end_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Occurrences</label>
                <input type="number" class="form-control form-control-sm" name="max_occurrences" value="{{ old('max_occurrences') }}" min="1">
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="auto_post" id="auto_post" value="1" @checked(old('auto_post'))>
                    <label class="form-check-label" for="auto_post">Auto-post</label>
                </div>
            </div>

            <div class="col-12"><hr><h6>Template Data</h6></div>

            <div id="journalEntryData">
                <div class="col-12">
                    <label class="form-label">Narration</label>
                    <input type="text" class="form-control form-control-sm" name="template_data[narration]" value="{{ old('template_data.narration') }}" placeholder="Journal narration">
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label">Journal Type</label>
                    <select class="form-select form-select-sm" name="template_data[journal_type]">
                        <option value="journal">Journal</option>
                        <option value="adjustment">Adjustment</option>
                        <option value="contra">Contra</option>
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control form-control-sm" name="template_data[currency]" value="{{ old('template_data.currency', 'BDT') }}" maxlength="3">
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label">Journal Lines (JSON)</label>
                    <textarea class="form-control form-control-sm font-monospace" name="template_data[lines]" rows="5" placeholder='[{"coa_id": 1, "debit": 1000, "credit": 0}, {"coa_id": 2, "debit": 0, "credit": 1000}]'>{{ is_array(old('template_data.lines')) ? json_encode(old('template_data.lines'), JSON_PRETTY_PRINT) : old('template_data.lines') }}</textarea>
                    <small class="text-muted">JSON array of journal lines with coa_id, debit, credit.</small>
                </div>
            </div>

            <div id="invoiceData" style="display:none;">
                <div class="col-md-6">
                    <label class="form-label">Party ID (Customer)</label>
                    <input type="number" class="form-control form-control-sm" name="template_data[party_id]" value="{{ old('template_data.party_id') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Invoice Type</label>
                    <select class="form-select form-select-sm" name="template_data[invoice_type]">
                        <option value="other">Other</option>
                        <option value="course_fee">Course Fee</option>
                        <option value="admission">Admission</option>
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label">Items (JSON)</label>
                    <textarea class="form-control form-control-sm font-monospace" name="template_data[items]" rows="4" placeholder='[{"description": "Service", "amount": 5000}]'>{{ is_array(old('template_data.items')) ? json_encode(old('template_data.items'), JSON_PRETTY_PRINT) : old('template_data.items') }}</textarea>
                </div>
            </div>

            <div id="expensePaymentData" style="display:none;">
                <div class="col-12">
                    <label class="form-label">Template Data (JSON)</label>
                    <textarea class="form-control form-control-sm font-monospace" name="template_data[raw]" rows="5" placeholder='{"account_id": 1, "amount": 5000, "description": "Monthly expense"}'>{{ old('template_data.raw') }}</textarea>
                    <small class="text-muted">JSON payload for this transaction type.</small>
                </div>
            </div>

            <div class="col-12"><hr></div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Create template</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.recurring-templates.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const frequency = document.getElementById('frequency');
    const cronGroup = document.getElementById('cronGroup');
    const typeRadios = document.querySelectorAll('input[name="transaction_type"]');
    const journalData = document.getElementById('journalEntryData');
    const invoiceData = document.getElementById('invoiceData');
    const expensePaymentData = document.getElementById('expensePaymentData');

    function updateCron() {
        cronGroup.style.display = frequency.value === 'custom' ? '' : 'none';
    }

    function updateTypeData() {
        const type = document.querySelector('input[name="transaction_type"]:checked')?.value;
        journalData.style.display = (type === 'journal_entry') ? '' : 'none';
        invoiceData.style.display = (type === 'invoice' || type === 'vendor_bill') ? '' : 'none';
        expensePaymentData.style.display = (type === 'expense' || type === 'payment') ? '' : 'none';
    }

    frequency.addEventListener('change', updateCron);
    typeRadios.forEach(r => r.addEventListener('change', updateTypeData));
    updateCron();
    updateTypeData();
})();
</script>
@endsection
