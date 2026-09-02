@extends('layouts.admin')
@section('title', 'All Accounts — AccumenAI')
@section('content')
<style>
    .avatar-circle{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;background:#6c5ce7;flex-shrink:0}
    .avatar-img{width:42px;height:42px;border-radius:50%;object-fit:cover}
    .summary-card{border-radius:14px;padding:16px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #eef0f3}
    .summary-card .label{font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;font-weight:600}
    .summary-card .value{font-size:22px;font-weight:800;margin-top:4px}
    .account-card-mobile{border:1px solid #eef0f3;border-radius:14px;padding:14px;background:#fff;margin-bottom:12px}
    @media (min-width: 768px){ .account-card-mobile{display:none} }
    @media (max-width: 767.98px){ .desktop-table{display:none} }
</style>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">All Accounts</h4>
        <p class="page-header-desc">Manage all global user accounts across the platform</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.users.bin') }}"><i class="bi bi-trash-fill"></i> Recycle Bin @if(($summary['deleted'] ?? 0) > 0)<span class="badge bg-danger ms-1">{{ $summary['deleted'] }}</span>@endif</a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Total Accounts</div><div class="value">{{ number_format($summary['total'] ?? 0) }}</div><div class="text-muted small">{{ $items->total() }} shown</div></div></div>
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Active</div><div class="value text-success">{{ number_format($summary['active'] ?? 0) }}</div><div class="text-muted small">status = active</div></div></div>
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Banned / Suspended</div><div class="value text-warning">{{ number_format($summary['banned'] ?? 0) }}</div><div class="text-muted small">status = inactive</div></div></div>
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Deleted</div><div class="value text-secondary">{{ number_format($summary['deleted'] ?? 0) }}</div><div class="text-muted small">in recycle bin</div></div></div>
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Unverified</div><div class="value text-danger">{{ number_format($summary['unverified'] ?? 0) }}</div><div class="text-muted small">email not verified</div></div></div>
    <div class="col-6 col-lg-2"><div class="summary-card"><div class="label">Multi-Business</div><div class="value text-primary">{{ $items->filter(fn($u)=>($u->_e26_total_memberships ?? 0) > 1)->count() }} <span class="fs-6">on page</span></div><div class="text-muted small">>1 businesses</div></div></div>
</div>

@if($errors->any())<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}</div>@endif
@if(session('status'))<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('status') }}</div>@endif

{{-- Search + Filters --}}
<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.users.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:220px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name, email, phone or account ID..." value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '')==='active')>Active</option>
                    <option value="inactive" @selected(in_array(($filters['status'] ?? ''), ['inactive','banned','suspended'], true))>Banned / Suspended</option>
                    <option value="deleted" @selected(($filters['status'] ?? '')==='deleted')>Deleted</option>
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Verification</label>
                <select class="form-select form-select-sm" name="verification">
                    <option value="">All</option>
                    <option value="verified" @selected(($filters['verification'] ?? '')==='verified')>Verified</option>
                    <option value="unverified" @selected(($filters['verification'] ?? '')==='unverified')>Unverified</option>
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:160px">
                <label class="form-label mb-1">Business</label>
                <select class="form-select form-select-sm" name="business">
                    <option value="">All</option>
                    <option value="has_business" @selected(($filters['business'] ?? '')==='has_business')>Has Business</option>
                    <option value="no_business" @selected(($filters['business'] ?? '')==='no_business')>No Business</option>
                    <option value="multiple" @selected(($filters['business'] ?? '')==='multiple')>Multiple Businesses</option>
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Sort</label>
                <select class="form-select form-select-sm" name="sort">
                    <option value="latest" @selected(($filters['sort'] ?? 'latest')==='latest')>Latest</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '')==='oldest')>Oldest</option>
                    <option value="name" @selected(($filters['sort'] ?? '')==='name')>Name A-Z</option>
                </select>
            </div>
            <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">
            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ $items->total() }} Accounts</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Server-side pagination · {{ $perPage }} per page</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-list-ol"></i> Show: {{ $perPage }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach([25,50,100] as $opt)
                    <li><a class="dropdown-item @if($perPage==$opt) active @endif" href="{{ request()->fullUrlWithQuery(['per_page'=>$opt,'page'=>1]) }}">{{ $opt }} @if($perPage==$opt)<i class="bi bi-check-lg ms-2"></i>@endif</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i></button>
                <button type="button" class="btn btn-outline-success" id="exportCsvBtn"><i class="bi bi-filetype-csv"></i> CSV</button>
            </div>
        </div>
    </div>

    {{-- Desktop Table --}}
    <div class="table-responsive desktop-table">
        <table class="table align-middle mb-0" id="accountsTable">
            <thead>
                <tr>
                    <th style="width:42px">#</th>
                    <th>Account</th>
                    <th>User UID</th>
                    <th>Email / Phone</th>
                    <th>Businesses</th>
                    <th>Status</th>
                    <th>Verification</th>
                    <th>Last Activity</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $u)
                <tr>
                    <td class="text-muted">{{ $items->firstItem() + $loop->index }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if(!empty($u->photo))
                                <img src="{{ Storage::disk('public')->url($u->photo) }}" class="avatar-img" alt="">
                            @else
                                <div class="avatar-circle">{{ strtoupper(substr($u->name ?? $u->email ?? 'U',0,1)) }}</div>
                            @endif
                            <div>
                                <a class="fw-semibold text-decoration-none" href="{{ route('admin.users.show',$u) }}">{{ $u->name }}</a>
                                <div class="text-muted small">#{{ $u->id }} · {{ $u->account_type }}</div>
                            </div>
                        </div>
                    </td>
                    <td><x-uid-with-copy :uid="$u->uid" /></td>
                    <td>
                        <div class="small">{{ $u->email ?? '—' }}</div>
                        <div class="text-muted small">{{ $u->phone ?? '—' }}</div>
                    </td>
                    <td>
                        @php $total = $u->_e26_total_memberships ?? $u->memberships_count; $active = $u->_e26_active_businesses ?? 0; @endphp
                        <a href="{{ route('admin.users.show',$u) }}" class="badge text-bg-{{ $total>1?'primary':'secondary' }} text-decoration-none" title="View businesses">{{ $total }} {{ $total===1?'Business':'Businesses' }}</a>
                        @if($total>1)<div class="text-muted small">Multiple businesses</div>@endif
                        @if(($u->_e26_owned_active ?? 0)>0)<div class="badge text-bg-warning mt-1">Owner of {{ $u->_e26_owned_active }} active</div>@endif
                        @if(!empty($u->_e26_roles) && $u->_e26_roles->isNotEmpty())<div class="text-muted small">{{ $u->_e26_roles->implode(', ') }}</div>@endif
                    </td>
                    <td>
                        @if($u->deleted_at)<span class="badge text-bg-secondary">Deleted</span>
                        @elseif($u->status==='active')<span class="badge text-bg-success">Active</span>
                        @else<span class="badge text-bg-warning">Suspended</span>@endif
                    </td>
                    <td>
                        @if($u->email_verified_at)<span class="badge text-bg-success">Verified</span>@else<span class="badge text-bg-danger">Unverified</span>@endif
                    </td>
                    <td class="small text-muted">
                        @if($u->_last_login){{ \Illuminate\Support\Carbon::parse($u->_last_login)->diffForHumans() }}@else — @endif
                        <div class="small">{{ $u->last_login_at?->format('d M Y H:i') ?? '' }}</div>
                    </td>
                    <td class="small text-muted">{{ $u->created_at->format('d M Y') }}</td>
                    <td class="text-end text-nowrap">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('admin.users.show',$u) }}"><i class="bi bi-eye me-2"></i> View</a></li>
                                @if($u->deleted_at)
                                    <li><button type="button" class="dropdown-item user-restore-btn" data-action="{{ route('admin.users.restore',$u) }}"><i class="bi bi-arrow-counterclockwise me-2 text-success"></i> Restore</button></li>
                                    <li><button type="button" class="dropdown-item text-danger user-force-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-deleted="{{ $u->_e26_deleted_businesses ?? 0 }}" data-total="{{ $u->_e26_total_memberships ?? 0 }}" data-owned-active="{{ $u->_e26_owned_active ?? 0 }}" data-roles="{{ $u->_e26_roles?$u->_e26_roles->implode(', '):'' }}" data-action="{{ route('admin.users.force-delete',$u) }}"><i class="bi bi-x-octagon me-2"></i> Permanent Delete</button></li>
                                @else
                                    @if($u->status==='active')
                                        <li><button type="button" class="dropdown-item text-warning user-suspend-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-action="{{ route('admin.users.suspend',$u) }}"><i class="bi bi-pause-circle me-2"></i> Suspend / Ban</button></li>
                                    @else
                                        <li><button type="button" class="dropdown-item text-success user-reactivate-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-action="{{ route('admin.users.reactivate',$u) }}"><i class="bi bi-play-circle me-2"></i> Unban / Reactivate</button></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item text-danger user-del-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-deleted="{{ $u->_e26_deleted_businesses ?? 0 }}" data-total="{{ $u->_e26_total_memberships ?? 0 }}" data-owned-active="{{ $u->_e26_owned_active ?? 0 }}" data-roles="{{ $u->_e26_roles?$u->_e26_roles->implode(', '):'' }}" data-action="{{ route('admin.users.destroy',$u) }}"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center py-5">
                    <div class="text-muted"><i class="bi bi-people fs-1 d-block mb-2"></i>
                    <h6>No accounts found</h6><p class="small">Try changing your search or filters.</p></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div class="p-2 d-md-none">
        @forelse($items as $u)
        <div class="account-card-mobile">
            <div class="d-flex align-items-center gap-2 mb-2">
                @if(!empty($u->photo))<img src="{{ Storage::disk('public')->url($u->photo) }}" class="avatar-img" alt="">@else<div class="avatar-circle">{{ strtoupper(substr($u->name ?? $u->email ?? 'U',0,1)) }}</div>@endif
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $u->name }}</div>
                    <div class="text-muted small">{{ $u->email }}</div>
                    <div class="text-muted small">{{ $u->phone }}</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show',$u) }}">View</a>
            </div>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge text-bg-{{ ($u->_e26_total_memberships ?? 0)>1?'primary':'secondary' }}">{{ $u->_e26_total_memberships ?? 0 }} Businesses</span>
                @if($u->deleted_at)<span class="badge text-bg-secondary">Deleted</span>@elseif($u->status==='active')<span class="badge text-bg-success">Active</span>@else<span class="badge text-bg-warning">Suspended</span>@endif
                @if($u->email_verified_at)<span class="badge text-bg-success">Verified</span>@else<span class="badge text-bg-danger">Unverified</span>@endif
                @if(($u->_e26_owned_active ?? 0)>0)<span class="badge text-bg-warning">Owner {{ $u->_e26_owned_active }}</span>@endif
            </div>
            <div class="small text-muted">Last Login: {{ $u->_last_login ? \Illuminate\Support\Carbon::parse($u->_last_login)->diffForHumans() : '—' }} · Created: {{ $u->created_at->format('d M Y') }}</div>
            <div class="mt-2 d-flex gap-1 flex-wrap">
                @if($u->deleted_at)
                    <button type="button" class="btn btn-sm btn-success user-restore-btn" data-action="{{ route('admin.users.restore',$u) }}"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                    <button type="button" class="btn btn-sm btn-outline-danger user-force-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-deleted="{{ $u->_e26_deleted_businesses ?? 0 }}" data-total="{{ $u->_e26_total_memberships ?? 0 }}" data-owned-active="{{ $u->_e26_owned_active ?? 0 }}" data-action="{{ route('admin.users.force-delete',$u) }}">Permanent Delete</button>
                @else
                    @if($u->status==='active')<button type="button" class="btn btn-sm btn-outline-warning user-suspend-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-action="{{ route('admin.users.suspend',$u) }}">Suspend</button>
                    @else<button type="button" class="btn btn-sm btn-outline-success user-reactivate-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-action="{{ route('admin.users.reactivate',$u) }}">Reactivate</button>@endif
                    <button type="button" class="btn btn-sm btn-outline-danger user-del-btn" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-active="{{ $u->_e26_active_businesses ?? 0 }}" data-deleted="{{ $u->_e26_deleted_businesses ?? 0 }}" data-total="{{ $u->_e26_total_memberships ?? 0 }}" data-owned-active="{{ $u->_e26_owned_active ?? 0 }}" data-action="{{ route('admin.users.destroy',$u) }}">Delete</button>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-5"><i class="bi bi-people fs-1 d-block mb-2"></i><h6>No accounts found</h6><p class="small">Try changing your search or filters.</p></div>
        @endforelse
    </div>

    <div class="mt-3 d-flex flex-column align-items-center gap-2">
        {{ $items->links('pagination::bootstrap-5') }}
        <span class="text-muted small">Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ $items->total() }} accounts ({{ $perPage }} per page)</span>
    </div>
</div>

{{-- Soft Delete Modal --}}
<div class="modal fade" id="userDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="userDeleteForm" data-ajax-enabled>
            @csrf @method('DELETE')
            <div class="modal-header"><h5 class="modal-title text-warning"><i class="bi bi-exclamation-triangle-fill"></i> Delete Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3"><i class="bi bi-info-circle"></i> <strong>Soft Delete</strong> moves the account to Recycle Bin. Businesses are <strong>NOT</strong> deleted. Sessions/tokens revoked, audit preserved.</div>
                <div class="card card-body bg-light small mb-3">
                    <div><strong>Account:</strong> <span id="ud_name" class="fw-bold"></span> <span id="ud_email" class="text-muted"></span></div>
                    <div class="mt-2"><strong>Businesses</strong> — Active: <span id="ud_active" class="badge text-bg-primary">0</span> · Deleted: <span id="ud_deleted" class="badge text-bg-secondary">0</span> · Total: <span id="ud_total" class="badge text-bg-secondary">0</span></div>
                    <div class="small text-muted">Owner active: <span id="ud_owned"></span> · Roles: <span id="ud_roles"></span></div>
                    <div id="ud_block_soft" class="alert alert-warning mt-2 mb-0 py-2 small d-none"><i class="bi bi-shield-check"></i> Owns <span class="ud_owned_count"></span> active business(es) — permanent delete will be blocked until resolved.</div>
                    <div id="ud_ok_soft" class="alert alert-success mt-2 mb-0 py-2 small d-none">No active ownership blocking.</div>
                </div>
                <div class="alert alert-warning small"><strong>Separate from business deletion.</strong> Deleting this global user does NOT delete its businesses.</div>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
                <div class="form-text">Verified via <code>PasswordHash::safeCheck</code> — never logged. Audit <code>account_soft_deleted</code>.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning"><i class="bi bi-trash3"></i> Move to Recycle Bin</button></div>
        </form>
    </div>
</div>

{{-- Suspend Modal --}}
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="suspendForm" data-ajax-enabled>
            @csrf
            <div class="modal-header"><h5 class="modal-title text-warning"><i class="bi bi-pause-circle"></i> Suspend Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="card card-body bg-light small mb-3">
                    <div>You are about to suspend:</div>
                    <div class="fw-bold" id="suspend_name"></div>
                    <div class="text-muted small" id="suspend_email"></div>
                    <div class="text-muted small mt-1">Businesses: <span id="suspend_businesses"></span></div>
                </div>
                <div class="alert alert-warning small">This will prevent the account from accessing the platform. <strong>Businesses owned by this account will NOT be deleted.</strong></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning"><i class="bi bi-pause-circle"></i> Suspend Account</button></div>
        </form>
    </div>
</div>

{{-- Reactivate Modal --}}
<div class="modal fade" id="reactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="reactivateForm" data-ajax-enabled>
            @csrf
            <div class="modal-header"><h5 class="modal-title text-success"><i class="bi bi-play-circle"></i> Reactivate Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="card card-body bg-light small mb-3">
                    <div>Reactivate:</div>
                    <div class="fw-bold" id="reactivate_name"></div>
                    <div class="text-muted small" id="reactivate_email"></div>
                </div>
                <div class="alert alert-success small">The account will be allowed to sign in again.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="bi bi-play-circle"></i> Reactivate</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function(){
    // Auto-submit filters
    var filterForm=document.querySelector('.filter-layout');
    if(filterForm){ filterForm.querySelectorAll('select[name]').forEach(function(s){ s.addEventListener('change', function(){ filterForm.submit(); }); }); }
    // Export CSV
    var csvBtn=document.getElementById('exportCsvBtn');
    if(csvBtn){ csvBtn.addEventListener('click', function(){
        var table=document.getElementById('accountsTable'); if(!table) return;
        var out=[]; var headers=[]; table.querySelectorAll('thead th').forEach(function(th,i){ if(i>0) headers.push(th.textContent.trim()); });
        out.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(function(tr){
            var cells=tr.querySelectorAll('td'); if(!cells.length) return;
            var row=[]; for(var i=1;i<cells.length-1;i++){ row.push('"'+cells[i].textContent.trim().replace(/"/g,'""')+'"'); }
            out.push(row.join(','));
        });
        var blob=new Blob(['\ufeff'+out.join('\r\n')],{type:'text/csv;charset=utf-8;'});
        var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='all-accounts.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a);
    }); }

    function clearErrors(form){
        form.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
    }
    function handleAjaxForm(form, successMsg){
        if(!window.Monetix||!Monetix.request) return;
        clearErrors(form);
        var pw=form.querySelector('input[name="password"]');
        if(pw){ pw.value=(pw.value||'').trim(); if(!pw.value){ pw.classList.add('is-invalid'); var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent='Password is required.'; pw.parentNode.insertBefore(m,pw.nextSibling); return; } }
        var btn=form.querySelector('[type="submit"]'); btn.disabled=true;
        var restore=Monetix.loading(btn, 'Processing…');
        var url=form.getAttribute('data-action-url')||form.action;
        var method=form.querySelector('input[name="_method"]')?.value || 'POST';
        Monetix.request(url,{method:method,body:new FormData(form)}).then(function(res){
            if(restore) restore(); btn.disabled=false;
            if(res&&res.errors){ Object.keys(res.errors).forEach(function(k){ var f=form.querySelector('[name="'+k+'"]'); if(f){ f.classList.add('is-invalid'); var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent=(res.errors[k]||[]).join(', '); f.parentNode.insertBefore(m,f.nextSibling);} }); if(Monetix.toast&&res.message) Monetix.toast(res.message,'danger'); return; }
            if(res&&res.success===false){ if(Monetix.toast) Monetix.toast(res.message||'Could not complete.','danger'); return; }
            var modalEl=form.closest('.modal'); var inst=modalEl?bootstrap.Modal.getInstance(modalEl):null; if(inst) inst.hide();
            var p=form.querySelector('input[name="password"]'); if(p) p.value='';
            if(Monetix.toast) Monetix.toast(res&&res.message||successMsg,'success');
            if(Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
        }).catch(function(err){ if(restore) restore(); btn.disabled=false; console.error('[form] failed',err); if(Monetix.toast) Monetix.toast('Network error — please try again.','danger'); });
    }

    // Delete (soft)
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.user-del-btn'); if(!btn) return;
        var modal=document.getElementById('userDeleteModal'); var form=document.getElementById('userDeleteForm');
        if(!modal||!form) return;
        form.action=btn.getAttribute('data-action'); form.setAttribute('data-action-url', btn.getAttribute('data-action'));
        document.getElementById('ud_name').textContent=btn.getAttribute('data-name')||'';
        document.getElementById('ud_email').textContent=btn.getAttribute('data-email')?('('+btn.getAttribute('data-email')+')'):'';
        document.getElementById('ud_active').textContent=btn.getAttribute('data-active')||'0';
        document.getElementById('ud_deleted').textContent=btn.getAttribute('data-deleted')||'0';
        document.getElementById('ud_total').textContent=btn.getAttribute('data-total')||'0';
        document.getElementById('ud_owned').textContent=btn.getAttribute('data-owned-active')||'0';
        document.getElementById('ud_roles').textContent=btn.getAttribute('data-roles')||'—';
        var owned=parseInt(btn.getAttribute('data-owned-active')||'0',10);
        var block=document.getElementById('ud_block_soft'); var ok=document.getElementById('ud_ok_soft');
        if(block){ if(owned>0){ block.classList.remove('d-none'); block.querySelector('.ud_owned_count').textContent=owned; } else block.classList.add('d-none'); }
        if(ok){ if(owned>0) ok.classList.add('d-none'); else ok.classList.remove('d-none'); }
        clearErrors(form); var pw=form.querySelector('input[name="password"]'); if(pw){ pw.value=''; pw.classList.remove('is-invalid'); }
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    document.getElementById('userDeleteForm')?.addEventListener('submit', function(e){ e.preventDefault(); handleAjaxForm(this, 'Account moved to recycle bin.'); });

    // Suspend
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.user-suspend-btn'); if(!btn) return;
        var modal=document.getElementById('suspendModal'); var form=document.getElementById('suspendForm');
        if(!modal||!form) return;
        form.action=btn.getAttribute('data-action'); form.setAttribute('data-action-url', btn.getAttribute('data-action'));
        document.getElementById('suspend_name').textContent=btn.getAttribute('data-name')||'';
        document.getElementById('suspend_email').textContent=btn.getAttribute('data-email')||'';
        document.getElementById('suspend_businesses').textContent=(btn.getAttribute('data-active')||'0')+' active';
        clearErrors(form);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    document.getElementById('suspendForm')?.addEventListener('submit', function(e){ e.preventDefault(); handleAjaxForm(this, 'Account suspended.'); });

    // Reactivate
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.user-reactivate-btn'); if(!btn) return;
        var modal=document.getElementById('reactivateModal'); var form=document.getElementById('reactivateForm');
        if(!modal||!form) return;
        form.action=btn.getAttribute('data-action'); form.setAttribute('data-action-url', btn.getAttribute('data-action'));
        document.getElementById('reactivate_name').textContent=btn.getAttribute('data-name')||'';
        document.getElementById('reactivate_email').textContent=btn.getAttribute('data-email')||'';
        clearErrors(form);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    document.getElementById('reactivateForm')?.addEventListener('submit', function(e){ e.preventDefault(); handleAjaxForm(this, 'Account reactivated.'); });

    // Clear stale on hide
    document.querySelectorAll('.modal').forEach(function(m){
        m.addEventListener('hidden.bs.modal', function(){
            var f=m.querySelector('form'); if(!f) return;
            clearErrors(f); var pw=f.querySelector('input[name="password"]'); if(pw) pw.value='';
        });
    });
})();
</script>
@endpush
@endsection
