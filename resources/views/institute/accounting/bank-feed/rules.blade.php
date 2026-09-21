@extends('layouts.standalone')

@section('title', 'Bank Rules — AccumenAI')
@section('page_title', 'Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Bank Rules</h4>
    <p>Auto-categorize transactions based on patterns.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRuleModal"><i class="bi bi-plus-lg me-1"></i>New Rule</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Name</th>
                    <th>Pattern</th>
                    <th>Amount</th>
                    <th>Direction</th>
                    <th>Action</th>
                    <th>Applied</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr>
                        <td>{{ $rule->priority }}</td>
                        <td>{{ $rule->name }}</td>
                        <td>
                            <code class="small">{{ $rule->pattern_field }} {{ $rule->pattern_type }} "{{ $rule->pattern_value }}"</code>
                        </td>
                        <td>
                            @if($rule->amount_operator)
                                {{ $rule->amount_operator }} {{ number_format($rule->amount_min, 2) }}
                                @if($rule->amount_max) — {{ number_format($rule->amount_max, 2) }}@endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $rule->direction ? ucfirst($rule->direction) : 'Any' }}</td>
                        <td><span class="badge text-bg-light border">{{ $rule->action_type }}</span></td>
                        <td>{{ $rule->times_applied }}</td>
                        <td>
                            @if($rule->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary edit-rule-btn"
                                    data-rule="{{ json_encode($rule) }}"
                                    data-bs-toggle="modal" data-bs-target="#editRuleModal"
                                    title="Edit"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('accounting.bank-feed.rules.destroy', $rule) }}" class="d-inline" data-confirm="Delete this rule?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No rules defined.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create Rule Modal -->
<div class="modal fade" id="createRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('accounting.bank-feed.rules.store') }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">New Bank Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="name" required maxlength="200">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control form-control-sm" name="priority" value="100" min="1" max="9999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Action <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="action_type" required>
                                <option value="categorize">Categorize (set account)</option>
                                <option value="ignore">Ignore</option>
                            </select>
                        </div>
                        <div class="col-12"><h6>Match Criteria</h6></div>
                        <div class="col-md-3">
                            <label class="form-label">Field</label>
                            <select class="form-select form-select-sm" name="pattern_field">
                                <option value="description">Description</option>
                                <option value="reference">Reference</option>
                                <option value="counterparty">Counterparty</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select class="form-select form-select-sm" name="pattern_type">
                                <option value="contains">Contains</option>
                                <option value="starts_with">Starts with</option>
                                <option value="ends_with">Ends with</option>
                                <option value="exact">Exact</option>
                                <option value="regex">Regex</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Value <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="pattern_value" required maxlength="500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount filter</label>
                            <select class="form-select form-select-sm" name="amount_operator">
                                <option value="">Any</option>
                                <option value="=">=</option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                                <option value="between">Between</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount_min" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount_max" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Direction</label>
                            <select class="form-select form-select-sm" name="direction">
                                <option value="">Any</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GL Account</label>
                            <select class="form-select form-select-sm" name="account_id">
                                <option value="">None</option>
                                @foreach ($accounts as $acct)
                                    <option value="{{ $acct->id }}">{{ $acct->code }} — {{ $acct->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Narration</label>
                            <input type="text" class="form-control form-control-sm" name="narration" maxlength="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Create Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editRuleForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="name" id="edit_name" required maxlength="200">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control form-control-sm" name="priority" id="edit_priority" min="1" max="9999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Action <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="action_type" id="edit_action_type" required>
                                <option value="categorize">Categorize</option>
                                <option value="ignore">Ignore</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Field</label>
                            <select class="form-select form-select-sm" name="pattern_field" id="edit_pattern_field">
                                <option value="description">Description</option>
                                <option value="reference">Reference</option>
                                <option value="counterparty">Counterparty</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select class="form-select form-select-sm" name="pattern_type" id="edit_pattern_type">
                                <option value="contains">Contains</option>
                                <option value="starts_with">Starts with</option>
                                <option value="ends_with">Ends with</option>
                                <option value="exact">Exact</option>
                                <option value="regex">Regex</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Value <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="pattern_value" id="edit_pattern_value" required maxlength="500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount filter</label>
                            <select class="form-select form-select-sm" name="amount_operator" id="edit_amount_operator">
                                <option value="">Any</option>
                                <option value="=">=</option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                                <option value="between">Between</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount_min" id="edit_amount_min" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount_max" id="edit_amount_max" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Direction</label>
                            <select class="form-select form-select-sm" name="direction" id="edit_direction">
                                <option value="">Any</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GL Account</label>
                            <select class="form-select form-select-sm" name="account_id" id="edit_account_id">
                                <option value="">None</option>
                                @foreach ($accounts as $acct)
                                    <option value="{{ $acct->id }}">{{ $acct->code }} — {{ $acct->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Narration</label>
                            <input type="text" class="form-control form-control-sm" name="narration" id="edit_narration" maxlength="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Update Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.querySelectorAll('.edit-rule-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const rule = JSON.parse(this.dataset.rule);
        document.getElementById('editRuleForm').action = '{{ url("accounting/bank-feed/rules") }}/' + rule.id;
        document.getElementById('edit_name').value = rule.name;
        document.getElementById('edit_priority').value = rule.priority;
        document.getElementById('edit_action_type').value = rule.action_type;
        document.getElementById('edit_pattern_field').value = rule.pattern_field;
        document.getElementById('edit_pattern_type').value = rule.pattern_type;
        document.getElementById('edit_pattern_value').value = rule.pattern_value;
        document.getElementById('edit_amount_operator').value = rule.amount_operator || '';
        document.getElementById('edit_amount_min').value = rule.amount_min || '';
        document.getElementById('edit_amount_max').value = rule.amount_max || '';
        document.getElementById('edit_direction').value = rule.direction || '';
        document.getElementById('edit_account_id').value = rule.account_id || '';
        document.getElementById('edit_narration').value = rule.narration || '';
    });
});
</script>
@endsection
