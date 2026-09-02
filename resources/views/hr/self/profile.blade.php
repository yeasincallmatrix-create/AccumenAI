@extends('layouts.institute')
@section('title','My Profile — HR')
@section('content')
<div class="standalone-heading">
    <h4>My Profile</h4>
    <a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card p-3">
    <div class="row g-2 small mb-3">
        <div class="col-md-4"><strong>Employee Code</strong><br>{{ $employee->employee_code }} <span class="text-muted">(HR-controlled)</span></div>
        <div class="col-md-4"><strong>Department</strong><br>{{ $employee->department?->name ?? '—' }} <span class="text-muted">(HR)</span></div>
        <div class="col-md-4"><strong>Designation</strong><br>{{ $employee->designation?->name ?? '—' }} <span class="text-muted">(HR)</span></div>
        <div class="col-md-4"><strong>Branch</strong><br>{{ $employee->branch?->name ?? '—' }} <span class="text-muted">(HR)</span></div>
        <div class="col-md-4"><strong>Status</strong><br>{{ $employee->employment_status }}</div>
        <div class="col-md-4"><strong>Joining</strong><br>{{ $employee->joining_date?->format('Y-m-d') ?? '—' }}</div>
    </div>
    <hr>
    <h6>Editable Profile</h6>
    <form method="POST" action="{{ route('hr.self.profile.update') }}" class="row g-2">
        @csrf @method('PUT')
        <div class="col-md-4"><label class="form-label small">First Name</label><input type="text" name="first_name" class="form-control form-control-sm" value="{{ old('first_name',$employee->first_name) }}"></div>
        <div class="col-md-4"><label class="form-label small">Middle Name</label><input type="text" name="middle_name" class="form-control form-control-sm" value="{{ old('middle_name',$employee->middle_name) }}"></div>
        <div class="col-md-4"><label class="form-label small">Last Name</label><input type="text" name="last_name" class="form-control form-control-sm" value="{{ old('last_name',$employee->last_name) }}"></div>
        <div class="col-md-6"><label class="form-label small">Phone</label>@include('partials.phone', ['name' => 'phone', 'id' => 'self_phone', 'value' => old('phone',$employee->phone), 'country' => $institute->country ?? null])</div>
        <div class="col-md-6"><label class="form-label small">Email</label><input type="email" name="email" class="form-control form-control-sm" value="{{ old('email',$employee->email) }}"></div>
        <div class="col-12"><label class="form-label small">Address</label><input type="text" name="address" class="form-control form-control-sm" value="{{ old('address',$employee->address) }}"></div>
        <div class="col-md-6"><label class="form-label small">Emergency Name</label><input type="text" name="emergency_contact_name" class="form-control form-control-sm" value="{{ old('emergency_contact_name',$employee->emergency_contact_name) }}"></div>
        <div class="col-md-6"><label class="form-label small">Emergency Phone</label>@include('partials.phone', ['name' => 'emergency_contact_phone', 'id' => 'self_emergency_phone', 'value' => old('emergency_contact_phone',$employee->emergency_contact_phone), 'country' => $institute->country ?? null])</div>
        <div class="col-12 mt-2"><button type="submit" class="btn btn-primary btn-sm">Update Profile</button></div>
    </form>
    <div class="mt-3 small text-muted">Sensitive fields (employee ID, salary, status, department, designation, branch) are HR-controlled and cannot be changed here.</div>
</div>
@endsection
