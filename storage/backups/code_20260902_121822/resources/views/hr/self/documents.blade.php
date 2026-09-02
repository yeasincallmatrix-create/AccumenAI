@extends('layouts.institute')
@section('title','My Documents — HR')
@section('content')
<div class="standalone-heading"><h4>My Documents</h4><a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card p-3 mb-3">
    <h6>Upload Document (where permitted)</h6>
    <form method="POST" action="{{ route('hr.self.documents.upload') }}" enctype="multipart/form-data" class="row g-2">
        @csrf
        <div class="col-md-4"><select name="category_id" class="form-select form-select-sm" required><option value="">Category</option>@foreach(\App\Models\DocumentCategory::where('is_active',true)->orderBy('name')->get()->filter(fn($c)=>$c->appliesTo('hr-employee')) as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><input type="file" name="file" class="form-control form-control-sm" required></div>
        <div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Upload</button></div>
    </form>
</div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Type</th><th>File</th><th>Status</th><th>Expiry</th><th></th></tr></thead>
        <tbody>
            @foreach($documents as $doc)<tr><td>{{ $doc->category?->name }}</td><td>{{ $doc->original_filename }} @if($doc->title) — {{ $doc->title }} @endif</td><td><span class="badge text-bg-{{ $doc->verification_status==='verified'?'success':($doc->verification_status==='rejected'?'danger':'secondary') }}">{{ $doc->verification_status }}</span> @if($doc->isExpired()) <span class="badge text-bg-danger">Expired</span> @elseif($doc->isExpiringSoon()) <span class="badge text-bg-warning">Expiring</span> @endif</td><td>{{ $doc->expiry_date?->format('Y-m-d') ?? '—' }}</td><td><a href="{{ route('hr.documents.download',$doc) }}" class="btn btn-sm btn-outline-primary">Download</a></td></tr>@endforeach
        </tbody>
    </table>
</div>
@endsection
