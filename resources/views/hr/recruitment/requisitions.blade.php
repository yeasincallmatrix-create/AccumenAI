@extends('layouts.institute')
@section('title','Requisitions — HR')
@section('content')
<div class="standalone-heading">
    <h4>Job Requisitions</h4>
    <a href="{{ route('hr.recruitment.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($canManage)
<div class="admin-card p-3 mb-3">
    <h6>Create Requisition</h6>
    <form method="POST" action="{{ route('hr.recruitment.requisitions.store') }}" class="row g-2">
        @csrf
        <div class="col-md-4"><input type="text" name="title" class="form-control form-control-sm" placeholder="Title *" required></div>
        <div class="col-md-2"><input type="number" name="openings" class="form-control form-control-sm" value="1" min="1"></div>
        <div class="col-md-3"><input type="text" name="required_skills" class="form-control form-control-sm" placeholder="Skills"></div>
        <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Create</button></div>
    </form>
</div>
@endif
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Title</th><th>Openings</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($requisitions as $req)
                <tr>
                    <td>{{ $req->title }}</td>
                    <td>{{ $req->openings }}</td>
                    <td><span class="badge text-bg-secondary">{{ $req->status }}</span></td>
                    <td class="text-nowrap">
                        @if($req->status==='draft')
                            <form method="POST" action="{{ route('hr.recruitment.requisitions.submit',$req) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-primary">Submit</button></form>
                        @endif
                        @if($req->status==='pending_approval' && $canApprove)
                            <form method="POST" action="{{ route('hr.recruitment.requisitions.decide',$req) }}" class="d-inline">@csrf<input type="hidden" name="decision" value="approved"><button type="submit" class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('hr.recruitment.requisitions.decide',$req) }}" class="d-inline">@csrf<input type="hidden" name="decision" value="rejected"><button type="submit" class="btn btn-sm btn-outline-danger">Reject</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $requisitions->links() }}</div>
</div>
@endsection
