@extends('layouts.institute')
@section('title', ($structure?'Edit':'Create').' Salary Structure — HR')
@section('content')
<div class="standalone-heading">
    <h4>{{ $structure?'Edit':'Create' }} Salary Structure</h4>
    <a href="{{ route('hr.salary-structures.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card p-3">
    <form method="POST" action="{{ $structure?route('hr.salary-structures.update',$structure):route('hr.salary-structures.store') }}">
        @csrf @if($structure)@method('PUT')@endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name *</label><input type="text" name="name" class="form-control form-control-sm" value="{{ old('name',$structure->name??'') }}" required></div>
            <div class="col-md-6"><label class="form-label">Code *</label><input type="text" name="code" class="form-control form-control-sm" value="{{ old('code',$structure->code??'') }}" required></div>
            <div class="col-md-4"><label class="form-label">Basic *</label><input type="number" step="0.01" name="basic_salary" class="form-control form-control-sm" value="{{ old('basic_salary',$structure->basic_salary??0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Housing</label><input type="number" step="0.01" name="housing_allowance" class="form-control form-control-sm" value="{{ old('housing_allowance',$structure->housing_allowance??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Medical</label><input type="number" step="0.01" name="medical_allowance" class="form-control form-control-sm" value="{{ old('medical_allowance',$structure->medical_allowance??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Transport</label><input type="number" step="0.01" name="transport_allowance" class="form-control form-control-sm" value="{{ old('transport_allowance',$structure->transport_allowance??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Other Allowance</label><input type="number" step="0.01" name="other_allowance" class="form-control form-control-sm" value="{{ old('other_allowance',$structure->other_allowance??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Overtime Rate</label><input type="number" step="0.01" name="overtime_rate" class="form-control form-control-sm" value="{{ old('overtime_rate',$structure->overtime_rate??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Bonus</label><input type="number" step="0.01" name="bonus_amount" class="form-control form-control-sm" value="{{ old('bonus_amount',$structure->bonus_amount??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Commission</label><input type="number" step="0.01" name="commission_amount" class="form-control form-control-sm" value="{{ old('commission_amount',$structure->commission_amount??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Deduction</label><input type="number" step="0.01" name="deduction_amount" class="form-control form-control-sm" value="{{ old('deduction_amount',$structure->deduction_amount??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Tax</label><input type="number" step="0.01" name="tax_deduction" class="form-control form-control-sm" value="{{ old('tax_deduction',$structure->tax_deduction??0) }}"></div>
            <div class="col-md-4"><label class="form-label">Effective From *</label><input type="date" name="effective_from" class="form-control form-control-sm" value="{{ old('effective_from',isset($structure)?$structure->effective_from->format('Y-m-d'):now()->toDateString()) }}" required></div>
            <div class="col-md-8"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control form-control-sm" value="{{ old('notes',$structure->notes??'') }}"></div>
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">{{ $structure?'Update':'Create' }}</button></div>
    </form>
</div>
@endsection
