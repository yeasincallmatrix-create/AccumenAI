@extends('layouts.institute')
@section('title','Payroll Periods — HR')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Payroll Periods <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Create and manage monthly payroll cycles.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('hr.payroll.reports') }}" class="btn btn-outline-primary btn-sm">Reports</a>
        <a href="{{ route('hr.payroll.register') }}" class="btn btn-outline-primary btn-sm">Register</a>
    </div>
</div>
@include('hr._payroll_tabs')

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($canManage)
<div class="admin-card p-3 mb-3">
    <h6>Create Period</h6>
    <form method="POST" action="{{ route('hr.payroll.periods.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name eg Jan 2026" required></div>
        <div class="col-md-3"><input type="date" name="start_date" class="form-control form-control-sm" value="{{ now()->startOfMonth()->toDateString() }}" required></div>
        <div class="col-md-3"><input type="date" name="end_date" class="form-control form-control-sm" value="{{ now()->endOfMonth()->toDateString() }}" required></div>
        <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Create</button></div>
    </form>
</div>
@endif
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th>Period</th><th>Status</th><th>Employees</th><th>Net</th><th></th></tr></thead>
            <tbody>
                @foreach($periods as $p)
                    <tr>
                        <td><a href="{{ route('hr.payroll.periods.show',$p) }}">{{ $p->name }}</a></td>
                        <td>{{ $p->start_date->format('Y-m-d') }} → {{ $p->end_date->format('Y-m-d') }}</td>
                        <td><span class="badge text-bg-{{ $p->status==='paid'?'success':($p->status==='approved'?'primary':'secondary') }}">{{ $p->status }}</span></td>
                        <td>{{ $p->total_employees }}</td>
                        <td>{{ number_format($p->total_net,2) }}</td>
                        <td><a href="{{ route('hr.payroll.periods.show',$p) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $periods->links() }}</div>
</div>
@endsection
