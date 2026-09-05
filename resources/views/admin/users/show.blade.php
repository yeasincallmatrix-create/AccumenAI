@extends('layouts.admin')
@section('title', 'User Details — AccumenAI')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Users</a></li><li class="breadcrumb-item active">{{ $user->name }}</li></ol></nav>
<div class="page-header"><div class="page-header-text"><h4 class="page-header-title">{{ $user->name }}</h4><p class="page-header-desc">{{ $user->email }} · {{ $user->account_type }} · {{ $user->status }}</p></div><div class="page-header-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-left"></i> Back</a></div></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card">
    <div class="card-body">
        <h6>Global Account</h6>
        <div class="small text-muted">ID {{ $user->id }} · UUID {{ $user->uuid }} · Created {{ $user->created_at }}</div>
        <div class="mt-2"><x-uid-with-copy :uid="$user->uid" label="User UID" /></div>
        <div class="mt-3"><strong>Businesses / Memberships ({{ $user->memberships?->count() ?? 0 }})</strong></div>
        <div class="table-responsive mt-2">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Institute</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                    @forelse($user->memberships as $m)
                    <tr>
                        <td>{{ $m->institution->name ?? '— (#'.$m->institution_id.')' }} @if($m->institution?->deleted_at)<span class="badge text-bg-secondary">trashed</span>@endif</td>
                        <td>{{ $m->role->name ?? $m->role_id }}</td>
                        <td><span class="badge text-bg-{{ $m->status==='active'?'success':'secondary' }}">{{ $m->status }}</span> @if($m->deleted_at)<span class="badge text-bg-warning">soft-deleted</span>@endif</td>
                        <td class="text-muted small">{{ $m->created_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    @empty<tr><td colspan="4" class="text-muted text-center py-3">No memberships — orphaned account (safe to keep or delete via explicit user deletion).</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mt-3 small"><i class="bi bi-shield-check"></i> Deleting a Business never deletes this global account. Use the explicit <strong>Delete Account</strong> action on the Users list only — it will be blocked if this account still owns active businesses.</div>
    </div>
</div>
@endsection
