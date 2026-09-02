@extends('layouts.standalone')

@section('title', $account ? 'Edit Account — AccumenAI' : 'New Account — AccumenAI')
@section('page_title', $account ? 'Edit Account' : 'New Account')

@section('content')

<div class="standalone-heading">
    <h4>{{ $account ? 'Edit Account' : 'New Account' }}</h4>
    <p>Account codes must be unique within the institute scope. Deactivating an account is preferred over deleting one with journal activity.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $account ? route('finance.chart-of-accounts.update', $account) : route('finance.chart-of-accounts.store') }}">
        @csrf
        @if ($account) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="code" value="{{ old('code', $account?->code) }}" required maxlength="30">
            </div>
            <div class="col-md-8">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $account?->name) }}" required maxlength="150">
            </div>

            <div class="col-md-4">
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="type" id="accountTypeSelect" required>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type', $account?->type) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Group <small class="text-muted">(Category)</small></label>
                <select class="form-select form-select-sm" name="account_group_id" id="accountGroupSelect">
                    <option value="">— Auto (by Type) —</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" data-category="{{ $group->category }}" @selected((string) old('account_group_id', $account?->account_group_id) === (string) $group->id)>{{ $group->code }} — {{ $group->name }} ({{ $group->category }})</option>
                    @endforeach
                </select>
                <small class="text-muted">Wired to Type — auto-selects matching category.</small>
            </div>
            <div class="col-md-4">
                <label class="form-label">Parent account <small class="text-muted">(Subcategory)</small></label>
                <select class="form-select form-select-sm" name="parent_id" id="parentAccountSelect">
                    <option value="">— None (top-level) —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" data-type="{{ $parent->type }}" @selected((string) old('parent_id', $account?->parent_id) === (string) $parent->id)>{{ $parent->code }} — {{ $parent->name }} [{{ $parent->type }}]</option>
                    @endforeach
                </select>
                <small class="text-muted" id="parentHelp">Only parents of selected Type are selectable.</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">Cash Flow Category <small class="text-muted">(counterpart)</small></label>
                <select class="form-select form-select-sm" name="cash_flow_category">
                    <option value="" @selected(old('cash_flow_category', $account?->cash_flow_category) === null || old('cash_flow_category', $account?->cash_flow_category) === '')>— Not Classified —</option>
                    <option value="operating" @selected(old('cash_flow_category', $account?->cash_flow_category) === 'operating')>Operating</option>
                    <option value="investing" @selected(old('cash_flow_category', $account?->cash_flow_category) === 'investing')>Investing</option>
                    <option value="financing" @selected(old('cash_flow_category', $account?->cash_flow_category) === 'financing')>Financing</option>
                </select>
                <small class="text-muted">For cash counterpart classification. Leave empty for cash/bank accounts.</small>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_cash" value="1" id="is_cash" @checked((bool) old('is_cash', $account?->is_cash))>
                    <label class="form-check-label" for="is_cash">Cash account</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_bank" value="1" id="is_bank" @checked((bool) old('is_bank', $account?->is_bank))>
                    <label class="form-check-label" for="is_bank">Bank account</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_receivable" value="1" id="is_receivable" @checked((bool) old('is_receivable', $account?->is_receivable))>
                    <label class="form-check-label" for="is_receivable">Receivable</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_payable" value="1" id="is_payable" @checked((bool) old('is_payable', $account?->is_payable))>
                    <label class="form-check-label" for="is_payable">Payable</label>
                </div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $account ? 'Save changes' : 'Create account' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.chart-of-accounts.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSel = document.getElementById('accountTypeSelect');
    const groupSel = document.getElementById('accountGroupSelect');
    const parentSel = document.getElementById('parentAccountSelect');
    if (!typeSel || !groupSel || !parentSel) return;

    function rewire() {
        const type = typeSel.value;
        // Wire Group: auto-select matching category, disable mismatched
        let hasVisibleGroup = false;
        Array.from(groupSel.options).forEach(opt => {
            if (!opt.value) return; // Auto option always visible
            const cat = opt.dataset.category;
            const match = cat === type;
            opt.hidden = !match;
            opt.disabled = !match;
            if (match) hasVisibleGroup = true;
        });
        // If current group selection is hidden/mismatched, reset to Auto or first match
        const selectedGroupOpt = groupSel.options[groupSel.selectedIndex];
        if (selectedGroupOpt && selectedGroupOpt.dataset.category && selectedGroupOpt.dataset.category !== type) {
            // Try to select the group that matches type
            const matchOpt = Array.from(groupSel.options).find(o => o.dataset.category === type);
            if (matchOpt) groupSel.value = matchOpt.value;
            else groupSel.value = '';
        }

        // Wire Parent (subcategory): only show parents of same type
        let visibleParentCount = 0;
        Array.from(parentSel.options).forEach(opt => {
            if (!opt.value) return;
            const pType = opt.dataset.type;
            const match = pType === type;
            opt.hidden = !match;
            opt.disabled = !match;
            if (match) visibleParentCount++;
        });
        // If selected parent is now hidden, reset to None
        const selParentOpt = parentSel.options[parentSel.selectedIndex];
        if (selParentOpt && selParentOpt.value && selParentOpt.dataset.type !== type) {
            parentSel.value = '';
        }
        const help = document.getElementById('parentHelp');
        if (help) {
            help.textContent = visibleParentCount > 0
                ? `Only parents of type “${type}” are selectable (${visibleParentCount} available).`
                : `No parent accounts for type “${type}” — will be top-level.`;
        }
    }

    typeSel.addEventListener('change', rewire);
    rewire(); // initial wire on load (edit mode)
});
</script>
@endpush
@endsection