@extends('layouts.institute')
@section('title','Vacancies — HR')
@section('content')
<div class="standalone-heading">
    <h4>Vacancies</h4>
    <a href="{{ route('hr.recruitment.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($canManage)
<div class="admin-card p-3 mb-3">
    <h6>Create Vacancy</h6>
    <form method="POST" action="{{ route('hr.recruitment.vacancies.store') }}" class="row g-2">
        @csrf
        <div class="col-md-4"><input type="text" name="title" class="form-control form-control-sm" placeholder="Title *" required></div>
        <div class="col-md-2"><input type="number" name="openings" class="form-control form-control-sm" value="1" min="1"></div>
        <div class="col-md-3"><input type="text" name="description" class="form-control form-control-sm" placeholder="Description"></div>
        <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Create</button></div>
    </form>
</div>
@endif
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Title</th><th>Openings</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($vacancies as $vac)
                <tr>
                    <td>{{ $vac->title }}</td>
                    <td>{{ $vac->openings }}</td>
                    <td><span class="badge text-bg-secondary">{{ $vac->status }}</span></td>
                    <td>
                        @if($canManage && !in_array($vac->status,['closed','cancelled']))
                            <form method="POST" action="{{ route('hr.recruitment.vacancies.status',$vac) }}" class="d-inline">@csrf<input type="hidden" name="status" value="published"><button type="submit" class="btn btn-sm btn-outline-success">Publish</button></form>
                            <form method="POST" action="{{ route('hr.recruitment.vacancies.status',$vac) }}" class="d-inline">@csrf<input type="hidden" name="status" value="closed"><button type="submit" class="btn btn-sm btn-outline-secondary">Close</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $vacancies->links() }}</div>
</div>
@endsection
