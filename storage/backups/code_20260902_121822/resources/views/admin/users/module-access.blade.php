@extends('layouts.admin')

@section('title', 'User Module Access — ' . $institute->name)

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Module Access — {{ $user->name ?? $user->email }}</h4>
        <p class="page-header-desc">{{ $institute->name }} — Control which modules this user sees. By default package/industry applies, but admin can override per user (like AI access).</p>
    </div>
    <a href="{{ route('admin.institutes.show', $institute) }}" class="btn btn-outline-secondary btn-sm">Back to Institute</a>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="admin-card">
    <form method="POST" action="{{ route('admin.institutes.users.modules.update', [$institute, $user->getKey(), $user instanceof \App\Models\User ? 'user' : 'institute_user']) }}">
        @csrf
        @method('PUT')
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Institute Has?</th>
                        <th>User Visible?</th>
                        <th>Enable</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allModules as $mod)
                        @php
                            $hasInstitute = in_array($mod->key, $instituteEnabled, true);
                            $isVisible = app(\App\Services\UserModuleAccessService::class)->isEnabledForUser($institute, $user, $mod->key);
                            $checked = $isVisible;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $mod->name }}</strong> <code>{{ $mod->key }}</code>
                                <div class="small text-muted">{{ $mod->key === 'hr' ? 'Includes Payroll, Performance, Training' : '' }}{{ $mod->key === 'inventory' ? 'Stock, Warehouses, Items' : '' }}{{ $mod->key === 'sales' ? 'Quotations, Orders, Deliveries' : '' }}{{ $mod->key === 'purchase' ? 'Orders, Quotations, Receipts' : '' }}</div>
                            </td>
                            <td>
                                @if($hasInstitute)
                                    <span class="badge text-bg-success">Yes</span>
                                @else
                                    <span class="badge text-bg-secondary">No (package/industry)</span>
                                @endif
                            </td>
                            <td>
                                @if($isVisible)
                                    <span class="badge text-bg-primary">Visible</span>
                                @else
                                    <span class="badge text-bg-warning">Hidden</span>
                                @endif
                                @if(array_key_exists($mod->key, $userOverrides))
                                    <span class="badge text-bg-info ms-1">Overridden</span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $mod->key }}" id="mod_{{ $mod->key }}" {{ $checked ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mod_{{ $mod->key }}">{{ $checked ? 'Enabled' : 'Disabled' }}</label>
                                </div>
                                @if(!$hasInstitute && $checked)
                                    <small class="text-success">Overridden — visible despite package/industry.</small>
                                @elseif($hasInstitute && !$checked)
                                    <small class="text-warning">Overridden — hidden despite package.</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <label class="form-label">Reason (optional)</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Disable HR for this cashier">
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save User Access</button>
            <a href="{{ route('admin.institutes.show', $institute) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="admin-card mt-3">
    <h6>How it works — package/industry default, admin override (like AI)</h6>
    <ul class="small text-muted mb-0">
        <li><strong>Default:</strong> Every user sees what the institute has via package + entitlements + industry. No override row = visible.</li>
        <li><strong>Revoke:</strong> Unchecking stores <code>user_module_access.enabled=false</code> — hides that module for this user only, even if institute has it.</li>
        <li><strong>Restore:</strong> Checking again removes the override — falls back to institute default. Cache flushed on save.</li>
        <li><strong>Guard:</strong> Per-user override can only <em>revoke</em> — cannot grant modules the institute doesn't have via package.</li>
        <li>Applies to <strong>all</strong>: <code>crm, hr (payroll/performance/training), inventory, sales, purchase, finance, education, reports, ai, accounting, notifications</code>.</li>
    </ul>
</div>
@endsection
