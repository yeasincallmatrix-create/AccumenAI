@extends('layouts.admin')
@section('title', 'User Recycle Bin — AccumenAI')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Users</a></li><li class="breadcrumb-item active">Recycle Bin</li></ol></nav>
<div class="page-header">
    <div class="page-header-text"><h4 class="page-header-title">User Recycle Bin</h4><p class="page-header-desc">{{ $items->total() }} trashed accounts</p></div>
    <div class="page-header-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-left"></i> Back to Users</a></div>
</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>User</th><th>Account Type</th><th>Businesses</th><th>Deleted At</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($items as $u)
                <tr>
                    <td>{{ $loop->iteration + ($items->firstItem()-1) }}</td>
                    <td><div class="fw-semibold">{{ $u->name }}</div><div class="text-muted small">{{ $u->email }}</div></td>
                    <td><span class="badge text-bg-secondary">{{ $u->account_type }}</span></td>
                    <td>
                        <span class="badge text-bg-{{ ($u->_e26_active_businesses ?? 0) > 0 ? 'warning' : 'secondary' }}">{{ $u->_e26_active_businesses ?? 0 }} active</span>
                        <span class="badge text-bg-secondary">{{ $u->_e26_deleted_businesses ?? 0 }} deleted</span>
                        <div class="text-muted small">Total: {{ $u->_e26_total_memberships ?? 0 }} · Owner active: {{ $u->_e26_owned_active ?? 0 }}</div>
                    </td>
                    <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($u->deleted_at)->format('d M Y H:i') }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-success user-restore-btn" data-action="{{ route('admin.users.restore', $u) }}" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger user-force-btn" title="Permanent Delete"
                            data-name="{{ $u->name }}" data-email="{{ $u->email }}"
                            data-active="{{ $u->_e26_active_businesses ?? 0 }}"
                            data-deleted="{{ $u->_e26_deleted_businesses ?? 0 }}"
                            data-total="{{ $u->_e26_total_memberships ?? 0 }}"
                            data-owned-active="{{ $u->_e26_owned_active ?? 0 }}"
                            data-roles="{{ $u->_e26_roles ? $u->_e26_roles->implode(', ') : '' }}"
                            data-action="{{ route('admin.users.force-delete', $u) }}"><i class="bi bi-x-octagon"></i></button>
                    </td>
                </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-4">No trashed accounts.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $items->links('pagination::bootstrap-5') }}</div>
</div>
<div class="modal fade" id="userForceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="userForceForm" data-ajax-enabled>
            @csrf @method('DELETE')
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> PERMANENT ACCOUNT DELETION</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="uf_blocked" class="alert alert-danger d-none">
                    <i class="bi bi-shield-lock-fill"></i> <strong>BLOCKED — Permanent deletion unavailable</strong>
                    <div class="small mt-1">This account still owns <span id="uf_block_count" class="fw-bold"></span> active business(es). Permanent deletion is blocked until the ownership situation is resolved.</div>
                    <div class="small mt-1">Transfer ownership or resolve those businesses before permanently deleting the account.</div>
                </div>
                <div id="uf_allowed" class="alert alert-warning d-none">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>This permanently deletes the global user account and its account-owned security, authentication and personal data.</strong>
                    <div class="small mt-1">Businesses/institutions, academic records, student data, certificates, exam results, and financial records are <strong>NOT</strong> affected. Sessions, tokens, OTPs, 2FA, memberships and personal data will be removed. This action cannot be undone.</div>
                    <div class="small mt-1 text-success"><i class="bi bi-shield-check"></i> <strong>Academic safety:</strong> Students, certificates, exam results, and academic history are fully preserved. Audit trail for business accountability is preserved.</div>
                </div>
                <div class="card card-body bg-light small mb-3">
                    <div><strong>Account</strong></div>
                    <div><span id="uf_name" class="fw-bold"></span></div>
                    <div><span id="uf_email" class="text-muted"></span></div>
                    <div class="mt-2"><strong>Businesses</strong></div>
                    <div>Active Businesses: <span id="uf_active" class="badge text-bg-primary">0</span> · Deleted Businesses: <span id="uf_deleted" class="badge text-bg-secondary">0</span> · Total Memberships: <span id="uf_total" class="badge text-bg-secondary">0</span></div>
                    <div class="small text-muted">Owner active: <span id="uf_owned"></span> · Roles: <span id="uf_roles"></span></div>
                    <div class="small text-muted mt-2"><strong>What gets deleted:</strong> Memberships, sessions, API tokens, OTP records, 2FA/TOTP secrets, password reset tokens, login attempts, identity audit logs, notification preferences, user activity logs, profile photo.</div>
                    <div class="small text-muted mt-1"><strong>What is preserved:</strong> Businesses/institutions, business financial records, students, certificates, exam results, academic history, other users, business audit trail (audit_logs, activity_logs), platform audit trail (anonymized).</div>
                </div>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
                <div class="form-text">Password verified via <code>PasswordHash::safeCheck</code> — never logged. Audit will record <code>account_force_deleted</code> with <code>user_id</code>/<code>user_email</code> only. No secrets retained.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" id="uf_submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> Delete permanently</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('click', function(e){
    var btn=e.target.closest('.user-restore-btn'); if(!btn) return;
    var url=btn.getAttribute('data-action'); if(!url) return; if(!confirm('Restore this account? Businesses are not restored automatically unless the service explicitly handles membership restore.')) return;
    var restore=window.Monetix&&Monetix.loading?Monetix.loading(btn,'Restoring…'):null;
    var headers={'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'};
    fetch(url,{method:'POST',headers:headers}).then(r=>r.json().then(j=>({ok:r.ok,j:j}))).then(res=>{
        if(restore) restore();
        if(!res.ok||res.j.success===false){ if(window.Monetix&&Monetix.toast) Monetix.toast(res.j.message||'Could not restore.','danger'); else alert(res.j.message||'Could not restore.'); return; }
        if(Monetix.toast) Monetix.toast(res.j.message||'Account restored — memberships restored per service contract.','success'); if(Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
    }).catch(function(err){ if(restore) restore(); console.error('[userRestore] failed',err); if(Monetix.toast) Monetix.toast('Network error — please try again.','danger'); });
});
document.addEventListener('click', function(e){
    var btn=e.target.closest('.user-force-btn'); if(!btn) return;
    var modal=document.getElementById('userForceModal'); var form=document.getElementById('userForceForm');
    if(!modal||!form) return;
    form.action=btn.getAttribute('data-action'); form.setAttribute('data-action-url', btn.getAttribute('data-action'));
    document.getElementById('uf_name').textContent=btn.getAttribute('data-name')||'';
    document.getElementById('uf_email').textContent=btn.getAttribute('data-email')?('('+btn.getAttribute('data-email')+')'):'';
    document.getElementById('uf_active').textContent=btn.getAttribute('data-active')||'0';
    document.getElementById('uf_deleted').textContent=btn.getAttribute('data-deleted')||'0';
    document.getElementById('uf_total').textContent=btn.getAttribute('data-total')||'0';
    document.getElementById('uf_owned').textContent=btn.getAttribute('data-owned-active')||'0';
    document.getElementById('uf_roles').textContent=btn.getAttribute('data-roles')||'—';
    var owned=parseInt(btn.getAttribute('data-owned-active')||'0',10);
    var blocked=document.getElementById('uf_blocked'); var allowed=document.getElementById('uf_allowed');
    var submit=document.getElementById('uf_submit');
    if(blocked && allowed){
        if(owned>0){
            blocked.classList.remove('d-none'); allowed.classList.add('d-none');
            document.getElementById('uf_block_count').textContent=owned;
            if(submit){ submit.disabled=true; submit.classList.add('disabled'); submit.title='Blocked — owns active businesses'; }
        } else {
            blocked.classList.add('d-none'); allowed.classList.remove('d-none');
            if(submit){ submit.disabled=false; submit.classList.remove('disabled'); submit.title=''; }
        }
    }
    // Clear stale errors + password (never reuse stale state)
    form.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
    form.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
    var pw=form.querySelector('input[name="password"]'); if(pw){ pw.value=''; pw.classList.remove('is-invalid'); }
    bootstrap.Modal.getOrCreateInstance(modal).show();
});
// Reset modal on hide (clear password + errors + warnings)
document.getElementById('userForceModal')?.addEventListener('hidden.bs.modal', function(){
    var form=document.getElementById('userForceForm');
    if(!form) return;
    form.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
    form.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
    var pw=form.querySelector('input[name="password"]'); if(pw) pw.value='';
});
var ufForm=document.getElementById('userForceForm');
if(ufForm){
    ufForm.addEventListener('submit', function(e){
        if(!window.Monetix||!Monetix.request) return;
        e.preventDefault();
        // Clear previous errors
        ufForm.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
        ufForm.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
        var pw=ufForm.querySelector('input[name="password"]'); if(pw) pw.value=(pw.value||'').trim();
        if(!pw||!pw.value){ pw.classList.add('is-invalid'); var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent='Password is required.'; pw.parentNode.insertBefore(m,pw.nextSibling); return; }
        var btn=ufForm.querySelector('[type="submit"]');
        if(btn.disabled) return; // blocked by active ownership
        btn.disabled=true;
        var restore=Monetix.loading(btn,'Deleting…');
        var url=ufForm.getAttribute('data-action-url')||ufForm.action;
        Monetix.request(url,{method:'DELETE',body:new FormData(ufForm)}).then(function(res){
            if(restore) restore(); btn.disabled=false;
            if(res&&res.errors){ Object.keys(res.errors).forEach(function(k){ var f=ufForm.querySelector('[name="'+k+'"]'); if(f){ f.classList.add('is-invalid'); var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent=(res.errors[k]||[]).join(', '); f.parentNode.insertBefore(m,f.nextSibling);} }); if(Monetix.toast&&res.message) Monetix.toast(res.message,'danger'); return; }
            if(res&&res.success===false){ if(Monetix.toast) Monetix.toast(res.message||'Could not delete.','danger'); return; }
            var m=bootstrap.Modal.getInstance(document.getElementById('userForceModal')); if(m) m.hide();
            // Reset password
            var p=ufForm.querySelector('input[name="password"]'); if(p) p.value='';
            if(Monetix.toast) Monetix.toast(res&&res.message||'Permanently deleted — account-related data removed, businesses untouched.','success');
            if(Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
        }).catch(function(err){ if(restore) restore(); btn.disabled=false; console.error('[userForceDelete] failed',err); if(Monetix.toast) Monetix.toast('Network error — please try again.','danger'); });
    });
}
</script>
@endpush
@endsection
