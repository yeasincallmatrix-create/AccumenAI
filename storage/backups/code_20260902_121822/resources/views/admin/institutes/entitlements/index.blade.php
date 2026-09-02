@extends('layouts.admin')

@section('title', $institute->name . ' — Subscription & Module Access — AccumenAI')

@section('content')
@php
    $packageName = $institute->package->name ?? 'FREE (legacy)';
    $packageSlug = $institute->package->slug ?? 'FREE';
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $institute->name }} — Subscription & Module Access</h4>
        <p class="page-header-desc">
            {{ $institute->institute_code ?? $institute->slug }} |
            Industry: <strong>{{ $institute->industry ? (\App\Support\IndustryRules::label($institute->country ?? '', $institute->industry) ?? $institute->industry) : '—' }}</strong>
            @if($institute->sub_industry) / {{ $institute->sub_industry }} @endif |
            Package: <strong>{{ $packageName }}</strong> <span class="badge bg-info ms-1">{{ $packageSlug }}</span> |
            Status: <span class="badge text-bg-{{ $institute->status === 'active' ? 'success' : 'secondary' }}">{{ $institute->status }}</span>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.show', $institute) }}"><i class="bi bi-arrow-left"></i> Back to Business Profile</a>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.institutes.entitlements.create', $institute) }}"><i class="bi bi-plus-lg"></i> Add Module</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Business Information --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-building"></i> Business Profile</div>
    </div>
    <div class="row g-3 p-3">
        <div class="col-md-3"><small class="text-muted d-block">Business / Institute</small><span class="fw-semibold">{{ $institute->name }}</span><br><small class="text-muted">{{ $institute->institute_code ?? $institute->slug }}</small></div>
        <div class="col-md-3"><small class="text-muted d-block">Industry</small><span class="fw-semibold">{{ $institute->industry ? (\App\Support\IndustryRules::label($institute->country ?? '', $institute->industry) ?? $institute->industry) : '—' }}</span></div>
        <div class="col-md-3"><small class="text-muted d-block">Sub-Industry</small><span class="fw-semibold">{{ $institute->sub_industry ?? '—' }}</span></div>
        <div class="col-md-3"><small class="text-muted d-block">Current Package</small><span class="badge bg-primary">{{ $packageName }}</span> <small class="text-muted">{{ $institute->subscription_expiry ? 'Expiry: '.\Illuminate\Support\Carbon::parse($institute->subscription_expiry)->format('d M Y') : '' }}</small></div>
    </div>
</div>

{{-- PACKAGE MODULES --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-box-seam"></i> Current Package Modules <span class="badge bg-secondary ms-2">{{ $packageName }}</span></div>
        <small class="text-muted">Read-only — managed via Packages</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Module</th>
                    <th style="width:120px" class="text-center">Key</th>
                    <th style="width:120px" class="text-center">Package</th>
                    <th style="width:140px" class="text-center">Effective</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allModules as $key => $module)
                    @php
                        $inPackage = isset($packageModules[$key]) && $packageModules[$key];
                        $isEffective = in_array($key, $enabledModules ?? [], true);
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $module->name }}</td>
                        <td class="text-center"><code>{{ $key }}</code></td>
                        <td class="text-center">
                            @if($inPackage)
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> ✓</span>
                            @else
                                <span class="badge bg-secondary">✕</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($isEffective && $inPackage)
                                <span class="badge bg-success-subtle text-success border">Package Module</span>
                            @elseif($isEffective)
                                <span class="badge bg-primary-subtle text-primary border">Add-on</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No modules.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- INDIVIDUAL MODULE ACCESS --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-puzzle-fill"></i> Additional Module Entitlements <span class="badge bg-primary ms-2">{{ $entitlements->count() }}</span> <span class="ms-2 small text-muted">Individual Module Access</span></div>
        <a href="{{ route('admin.institutes.entitlements.create', $institute) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Module</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Module</th>
                    <th style="width:90px">Type</th>
                    <th style="width:120px">Status</th>
                    <th style="width:110px">Start</th>
                    <th style="width:110px">Expiry</th>
                    <th style="width:110px">Effective</th>
                    <th style="width:140px">Billing</th>
                    <th style="width:120px">Granted By</th>
                    <th style="width:180px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entitlements as $ent)
                    @php
                        $moduleName = $allModules[$ent->module_key]->name ?? $ent->module_key;
                        $isGrant = (bool) $ent->is_grant;
                        $isEffective = in_array($ent->module_key, $enabledModules ?? [], true) && $isGrant;
                        // Determine effective badge
                        $effBadge = 'secondary'; $effLabel = '—';
                        if ($ent->status === 'revoked') { $effBadge='danger'; $effLabel='Revoked'; }
                        elseif ($ent->status === 'expired') { $effBadge='secondary'; $effLabel='Expired'; }
                        elseif ($ent->status === 'pending') { $effBadge='warning'; $effLabel='Pending'; if ($ent->starts_at && $ent->starts_at->isFuture()) $effLabel.=' (future)'; }
                        elseif (!$isGrant) { $effBadge='danger'; $effLabel='Denied'; }
                        elseif (!$isEffective) {
                            // Check industry vs dependency
                            $isIndustryOk = !($ent->module_key==='education' && $institute->industry && $institute->industry!=='education');
                            if (!$isIndustryOk) { $effBadge='warning'; $effLabel='Industry Blocked'; }
                            else {
                                // dependency check via service
                                $missing = app(\App\Services\ModuleAccessService::class)->checkDependencies($ent->module_key, $enabledModules ?? []);
                                if (!empty($missing)) { $effBadge='warning'; $effLabel='Dependency Blocked ('.implode(',', $missing).')'; }
                                else { $effBadge='secondary'; $effLabel='Blocked'; }
                            }
                        } else {
                            if ($ent->status==='trialing') { $effBadge='info'; $effLabel='Trialing'; }
                            elseif ($ent->status==='active') { $effBadge='success'; $effLabel='Active'; }
                            else { $effBadge='success'; $effLabel=ucfirst($ent->status); }
                        }
                        $statusBadge = match($ent->status){'active'=>'success','trialing'=>'info','pending'=>'warning','expired'=>'secondary','revoked'=>'danger', default=>'secondary'};
                    @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $moduleName }}</span><br>
                            <small class="text-muted"><code>{{ $ent->module_key }}</code></small>
                            @if($ent->deleted_at) <span class="badge bg-dark ms-1">soft-deleted</span> @endif
                            <br><small class="text-muted">Trial: {{ $ent->trial_starts_at ? $ent->trial_starts_at->format('d/m/Y') : '—' }} → {{ $ent->trial_ends_at ? $ent->trial_ends_at->format('d/m/Y') : '—' }}</small>
                        </td>
                        <td class="text-center">
                            @if($isGrant)
                                <span class="badge bg-success">Grant</span>
                            @else
                                <span class="badge bg-danger">Deny</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $statusBadge }}">{{ $ent->status }}</span></td>
                        <td class="small">{{ $ent->starts_at ? $ent->starts_at->format('d/m/Y') : '—' }}</td>
                        <td class="small">{{ $ent->ends_at ? $ent->ends_at->format('d/m/Y') : 'Permanent' }}</td>
                        <td><span class="badge bg-{{ $effBadge }}">{{ $effLabel }}</span></td>
                        <td class="small">
                            @if($ent->monthly_price) M: {{ $ent->monthly_price }}<br>@endif
                            @if($ent->yearly_price) Y: {{ $ent->yearly_price }}<br>@endif
                            {{ $ent->billing_cycle ?? '—' }}
                            @if($ent->discount_percent) <span class="badge bg-warning-subtle text-warning border">{{ $ent->discount_percent }}% off</span> @endif
                            @if($ent->auto_renew) <span class="badge bg-info-subtle text-info border">auto-renew</span> @endif
                        </td>
                        <td class="small">{{ $ent->grantedBy->email ?? $ent->granted_by ?? '—' }}<br><small class="text-muted">{{ $ent->created_at->format('d M Y') }}</small></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <!-- Revoke -->
                                <form method="POST" action="{{ route('admin.institutes.entitlements.destroy', [$institute, $ent]) }}" onsubmit="return confirm('Revoke this entitlement?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                </form>
                                <!-- Extend button triggers modal -->
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#extendModal{{ $ent->id }}">Extend</button>
                            </div>
                            <!-- Extend Modal -->
                            <div class="modal fade" id="extendModal{{ $ent->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST" action="{{ route('admin.institutes.entitlements.extend', [$institute, $ent]) }}">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Extend {{ $moduleName }} — current expiry: {{ $ent->ends_at ? $ent->ends_at->format('d/m/Y') : 'Permanent' }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-2">
                                                    <label class="form-label">Extend Option</label>
                                                    <select name="extend_option" class="form-select">
                                                        <option value="">— choose —</option>
                                                        <option value="1m">+ 1 month</option>
                                                        <option value="3m">+ 3 months</option>
                                                        <option value="6m">+ 6 months</option>
                                                        <option value="1y">+ 1 year</option>
                                                        <option value="custom">Custom date</option>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Custom Expiry Date</label>
                                                    <input type="date" name="ends_at" class="form-control">
                                                    <small class="text-muted">Leave blank if using preset.</small>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Trial End (if trial)</label>
                                                    <input type="date" name="trial_ends_at" class="form-control">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Notes</label>
                                                    <textarea name="notes" rows="2" class="form-control" placeholder="Reason for extend"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary btn-sm">Extend</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No individual modules — use “Add Module” to grant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODULE ACCESS HISTORY --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-clock-history"></i> Module Access History <span class="badge bg-secondary ms-2">{{ $history->count() }}</span></div>
        <small class="text-muted">Last 50 events — reuse module_access_logs</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:140px">Date</th>
                    <th style="width:120px">Module</th>
                    <th style="width:180px">Action</th>
                    <th>Actor</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td><code>{{ $log->module_key }}</code></td>
                        <td>
                            @php $acBadge = match($log->action){'entitlement_granted'=>'success','trial_started'=>'info','entitlement_revoked'=>'danger','entitlement_expired'=>'secondary','trial_expired'=>'warning','entitlement_extended'=>'primary','enable'=>'success','disable'=>'danger','package_added'=>'primary','package_removed'=>'warning', default=>'secondary'}; @endphp
                            <span class="badge bg-{{ $acBadge }}">{{ $log->action }}</span>
                        </td>
                        <td class="small">{{ $log->actor_id ?? 'System' }} @if($log->package) <span class="badge bg-light text-dark border">{{ $log->package->slug }}</span> @endif</td>
                        <td class="small text-muted">{{ $log->previous_state ?? '—' }} → {{ $log->new_state ?? '—' }} @if($log->notes) — {{ \Str::limit($log->notes, 80) }} @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No history yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
