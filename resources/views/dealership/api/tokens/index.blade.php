@extends('layouts.standalone')

@section('title', 'API Tokens — AccumenAI')
@section('page_title', 'API Tokens')

@section('content')
<div class="standalone-heading">
    <h4>API Tokens (এপিআই টোকেন)</h4>
    <p>Per-SR device tokens for the future mobile app.</p>
</div>

@include('dealership.api._nav')

@if(session('api_plaintext'))
<div class="alert alert-warning">
    <strong>সংরক্ষণ করুন — আর দেখা যাবে না (Save now — shown only once):</strong>
    <code>{{ session('api_plaintext') }}</code>
</div>
@endif

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Name</th><th>Abilities</th><th>Last Used</th><th>Expires</th><th>Revoked</th><th></th></tr></thead>
            <tbody>
                @forelse($tokens as $token)
                    <tr>
                        <td>{{ $token->sales_force_id }}</td><td>{{ $token->name }}</td>
                        <td>{{ implode(', ', $token->abilities ?? []) }}</td>
                        <td>{{ $token->last_used_at ?? '—' }}</td><td>{{ $token->expires_at ?? '—' }}</td>
                        <td>{{ $token->revoked_at ?? 'No' }}</td>
                        <td>@if(!$token->revoked_at)<form method="POST" action="{{ route('dealership.api.tokens.revoke', $token) }}">@csrf<button class="btn btn-sm btn-danger" type="submit">Revoke</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো টোকেন নেই (No tokens yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $tokens->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন টোকেন (Issue Token)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.api.tokens.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-5"><label class="form-label">Device Label</label><input type="text" name="name" class="form-control" placeholder="SR Mobile - Samsung A12" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Issue</button></div>
        </form>
    </div>
</div>
@endsection
