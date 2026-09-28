@extends('layouts.standalone')

@section('title', 'API Endpoints — AccumenAI')
@section('page_title', 'API Endpoints')

@section('content')
<div class="standalone-heading">
    <h4>API Endpoints (এন্ডপয়েন্ট তালিকা)</h4>
    <p>Discovery registry for the future mobile app.</p>
</div>

@include('dealership.api._nav')

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Key</th><th>Method</th><th>URI</th><th>Permission</th><th>Version</th><th>Enabled</th><th></th></tr></thead>
            <tbody>
                @forelse($endpoints as $ep)
                    <tr>
                        <td><code>{{ $ep->endpoint_key }}</code></td><td>{{ $ep->http_method }}</td>
                        <td><code>{{ $ep->uri }}</code></td><td>{{ $ep->required_permission ?? '—' }}</td>
                        <td>{{ $ep->version }}</td><td>{{ $ep->is_enabled ? 'Yes' : 'No' }}</td>
                        <td><form method="POST" action="{{ route('dealership.api.endpoints.toggle', $ep) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Toggle</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো এন্ডপয়েন্ট নেই (No endpoints yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $endpoints->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন এন্ডপয়েন্ট (Add Endpoint)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.api.endpoints.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">Key</label><input type="text" name="endpoint_key" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Method</label>
                <select name="http_method" class="form-select"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select>
            </div>
            <div class="col-md-3"><label class="form-label">URI</label><input type="text" name="uri" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Permission</label><input type="text" name="required_permission" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Save</button></div>
        </form>
    </div>
</div>
@endsection
