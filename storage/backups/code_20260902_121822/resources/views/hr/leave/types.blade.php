@extends('layouts.institute')

@section('title', 'Leave Types — HR')

@section('content')
<div class="standalone-heading"><h4>Leave Types & Policies</h4><p>Yearly allowance, carry-forward, approval requirement, active/inactive.</p></div>

<div class="admin-card p-3 mb-3">
    <h6>Create Leave Type</h6>
    <form method="POST" action="{{ route('hr.leave.types.store') }}" class="row g-2">
        @csrf
        <div class="col-md-2"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-1"><input type="text" name="code" class="form-control form-control-sm" placeholder="Code *" required></div>
        <div class="col-md-2"><input type="number" name="yearly_allowance" class="form-control form-control-sm" placeholder="Allowance" min="0"></div>
        <div class="col-md-2"><label class="small"><input type="checkbox" name="carry_forward" value="1"> Carry</label> <label class="small ms-2"><input type="checkbox" name="requires_approval" value="1" checked> Approval</label></div>
        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Allowance</th><th>Carry</th><th>Approval</th><th>Active</th><th></th></tr></thead>
            <tbody>
                @forelse($types as $t)
                    <tr>
                        <td>{{ $t->name }}</td><td><code>{{ $t->code }}</code></td><td>{{ $t->yearly_allowance }}</td>
                        <td>{{ $t->carry_forward ? 'Yes' : 'No' }}</td><td>{{ $t->requires_approval ? 'Yes' : 'No' }}</td>
                        <td><span class="badge {{ $t->is_active?'text-bg-success':'text-bg-secondary' }}">{{ $t->is_active?'Active':'Inactive' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('hr.leave.types.update', $t) }}" class="d-inline">@csrf @method('PUT')<button name="is_active" value="{{ $t->is_active ? 0 : 1 }}" class="btn btn-sm btn-outline-secondary">{{ $t->is_active?'Deactivate':'Activate' }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No leave types.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($types->hasPages())<div class="p-2 border-top">{{ $types->links() }}</div>@endif
</div>
@endsection
