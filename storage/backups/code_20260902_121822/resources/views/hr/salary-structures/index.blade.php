@extends('layouts.institute')
@section('title','Salary Structures — HR')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Salary Structures <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Compensation templates with earnings, deductions and net computation.</p>
    </div>
    @if($canManage)
        <a href="{{ route('hr.salary-structures.create') }}" class="btn btn-primary btn-sm">Create Structure</a>
    @endif
</div>
@include('hr._payroll_tabs')

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Basic</th><th>Net</th><th>Active</th><th></th></tr></thead>
            <tbody>
                @foreach($structures as $s)
                    <tr>
                        <td><code>{{ $s->code }}</code></td>
                        <td>{{ $s->name }}</td>
                        <td>{{ number_format($s->basic_salary,2) }}</td>
                        <td>{{ number_format($s->netSalary(),2) }}</td>
                        <td><span class="badge {{ $s->is_active?'text-bg-success':'text-bg-secondary' }}">{{ $s->is_active?'Active':'Inactive' }}</span></td>
                        <td>
                            @if($canManage)
                                <a href="{{ route('hr.salary-structures.edit',$s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('hr.salary-structures.toggle',$s) }}" class="d-inline">@csrf @method('PATCH')<button type="submit" class="btn btn-sm btn-outline-secondary">Toggle</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $structures->links() }}</div>
</div>
@endsection
