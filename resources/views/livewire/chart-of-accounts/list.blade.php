<div>
<div class="standalone-heading d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div class="flex-grow-1">
        <h4>Chart of Accounts</h4>
        <p>Accounts define the ledger. Codes are unique per institute scope; posted accounts cannot be deleted (deactivate instead).</p>
    </div>
    @if ($canManage)
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreateModal">
            <i class="bi bi-plus-lg me-1"></i>New Account
        </button>
    @endif
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Name or code">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" wire:model.live="filters.type">
                    <option value="">All types</option>
                    @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-secondary btn-sm mt-1" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                    <th>Type</th>
                    <th>Statement</th>
                    <th>Flags</th>
                    <th>Status</th>
                    @if ($canManage)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-muted">{{ $account->code }}</td>
                        <td>
                            <span class="fw-semibold">{{ $account->name }}</span>
                            @if ($account->parent)
                                <div class="text-muted small">Parent: {{ $account->parent->name }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($account->balance ?? 0, 2) }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($account->type) }}</span></td>
                        <td>
                            @php($statement = $account->reportStatement())
                            <span class="badge text-bg-{{ $statement === 'Balance Sheet' ? 'primary' : 'warning' }}" title="Appears on the {{ $statement }}">{{ $statement }}</span>
                        </td>
                        <td>
                            @if ($account->is_cash)<span class="badge text-bg-info me-1">Cash</span>@endif
                            @if ($account->is_bank)<span class="badge text-bg-info me-1">Bank</span>@endif
                            @if ($account->is_receivable)<span class="badge text-bg-warning me-1">Receivable</span>@endif
                            @if ($account->is_payable)<span class="badge text-bg-warning me-1">Payable</span>@endif
                            @if ($account->is_system)<span class="badge text-bg-secondary">System</span>@endif
                            @if ($account->is_global_flag ?? false)<span class="badge text-bg-dark ms-1">🔒 Global</span>@endif
                            @if (! empty($account->industries))<span class="badge text-bg-warning ms-1">{{ implode(', ', (array) $account->industries) }}</span>@endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $account->is_active ? 'success' : 'secondary' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                @if (in_array($account->type, ['asset', 'liability', 'equity'], true))
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openOpeningModal({{ $account->id }})" title="Edit opening balance">
                                        <i class="bi bi-wallet2"></i>
                                    </button>
                                @endif
                                <a href="{{ route('finance.chart-of-accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary"
                                   title="{{ ($account->is_editable ?? true) ? 'Edit account' : 'Edit shared account — definition is read-only, your own settings stay editable' }}">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                @if ($account->is_editable ?? true)
                                    <button class="btn btn-sm btn-outline-{{ $account->is_active ? 'warning' : 'success' }}" wire:click="toggle({{ $account->id }})">
                                        <i class="bi bi-{{ $account->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                    </button>
                                @endif
                                @if (! $account->is_system)
                                    <button class="btn btn-sm btn-outline-danger" wire:confirm="Delete this account?" wire:click="destroy({{ $account->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 9 : 8 }}" class="text-center text-muted py-4">No accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($accounts->hasPages())
        <div class="p-2 border-top">{{ $accounts->links() }}</div>
    @endif
</div>

@if ($showOpeningModal)
    <div class="modal fade show d-block" data-no-relocate tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="coaOpeningTitle" wire:click.self="closeOpeningModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="coaOpeningTitle">Edit Opening Balance</h6>
                    <button type="button" class="btn-close" wire:click="closeOpeningModal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @error('openingForm')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror

                    <div class="mb-3">
                        <div class="fw-semibold">{{ $openingForm['label'] }}</div>
                        <small class="text-muted">Opening balance is tenant data — it can be changed even on a global account.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Opening balance <small class="text-muted">(blank clears)</small></label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm @error('openingForm.balance') is-invalid @enderror" wire:model="openingForm.balance" placeholder="0.00">
                            <small class="text-muted">Balance sheet accounts only (asset, liability, equity): cash, bank, payables, capital ... Debit for assets, credit otherwise.</small>
                            @error('openingForm.balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening balance date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm @error('openingForm.date') is-invalid @enderror" wire:model="openingForm.date">
                            <small class="text-muted">Must fall inside an open fiscal year.</small>
                            @error('openingForm.date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeOpeningModal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="storeOpening" wire:loading.attr="disabled">
                        <i class="bi bi-check-lg me-1"></i>Save opening balance
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif
@if ($showCreateModal)
    <div class="modal fade show d-block" data-no-relocate tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="coaCreateTitle" wire:click.self="closeCreateModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="coaCreateTitle">New Account</h6>
                    <button type="button" class="btn-close" wire:click="closeCreateModal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @error('form')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm @error('form.code') is-invalid @enderror" wire:model="form.code" maxlength="30" placeholder="e.g. 1000.1">
                            @error('form.code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm @error('form.name') is-invalid @enderror" wire:model="form.name" maxlength="150">
                            @error('form.name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" wire:model.live="form.type">
                                @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Group <small class="text-muted">(Category)</small></label>
                            <select class="form-select form-select-sm @error('form.account_group_id') is-invalid @enderror" wire:model="form.account_group_id">
                                <option value="">— Auto (by Type) —</option>
                                @foreach ($createGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->code }} — {{ $group->name }} ({{ $group->category }})</option>
                                @endforeach
                            </select>
                            @error('form.account_group_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Parent account <small class="text-muted">(Subcategory)</small></label>
                            <select class="form-select form-select-sm @error('form.parent_id') is-invalid @enderror" wire:model="form.parent_id">
                                <option value="">— None (top-level) —</option>
                                @foreach ($createParents as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->code }} — {{ $parent->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only accounts of the selected type are listed.</small>
                            @error('form.parent_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        @if (in_array($form['type'] ?? null, ['asset', 'liability', 'equity'], true))
                        <div class="col-md-4">
                            <label class="form-label">Opening balance <small class="text-muted">(optional)</small></label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm @error('form.opening_balance') is-invalid @enderror" wire:model="form.opening_balance" placeholder="0.00">
                            <small class="text-muted">Balance sheet accounts only (asset, liability, equity): cash, bank, payables, capital ... Debit for assets, credit otherwise.</small>
                            @error('form.opening_balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Opening balance date</label>
                            <input type="date" class="form-control form-control-sm @error('form.opening_balance_date') is-invalid @enderror" wire:model="form.opening_balance_date">
                            <small class="text-muted">Must fall inside an open fiscal year.</small>
                            @error('form.opening_balance_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        @endif

                        <div class="col-md-4">
                            <label class="form-label">Cash Flow Category <small class="text-muted">(auto)</small></label>
                            <select class="form-select form-select-sm" wire:model="form.cash_flow_category" disabled>
                                <option value="">— Not Classified —</option>
                                <option value="operating">Operating</option>
                                <option value="investing">Investing</option>
                                <option value="financing">Financing</option>
                            </select>
                            <small class="text-muted">Derived from type (cleared for Cash/Bank). Reclassify on the account edit page if needed.</small>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" wire:model="form.is_cash" id="modalIsCash">
                                <label class="form-check-label" for="modalIsCash">Cash</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" wire:model="form.is_bank" id="modalIsBank">
                                <label class="form-check-label" for="modalIsBank">Bank</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" wire:model="form.is_receivable" id="modalIsReceivable">
                                <label class="form-check-label" for="modalIsReceivable">Receivable</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" wire:model="form.is_payable" id="modalIsPayable">
                                <label class="form-check-label" for="modalIsPayable">Payable</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeCreateModal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="store" wire:loading.attr="disabled">
                        <i class="bi bi-check-lg me-1"></i>Create account
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif
</div>
