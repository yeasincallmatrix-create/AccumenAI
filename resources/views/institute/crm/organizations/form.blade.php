@extends('layouts.standalone')

@section('title', $organization ? 'Edit Organization — AccumenAI' : 'New Organization — AccumenAI')
@section('page_title', $organization ? 'Edit Organization' : 'New Organization')

@section('content')

<div class="standalone-heading">
    <h4>{{ $organization ? 'Edit Organization' : 'New Organization' }}</h4>
    <p>Organization details are institute-scoped. Duplicate names are blocked automatically.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $organization ? route('crm.organizations.update', $organization) : route('crm.organizations.store') }}">
        @csrf
        @if ($organization) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $organization?->name) }}" required maxlength="191">
            </div>
            <div class="col-md-6">
                <label class="form-label">Industry</label>
                <input type="text" class="form-control form-control-sm" name="industry" value="{{ old('industry', $organization?->industry) }}" maxlength="120">
            </div>

            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-sm" name="email" value="{{ old('email', $organization?->email) }}" maxlength="191">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'crm_org_phone', 'value' => old('phone', $organization?->phone)])
            </div>
            <div class="col-md-4">
                <label class="form-label">Website</label>
                <input type="text" class="form-control form-control-sm" name="website" value="{{ old('website', $organization?->website) }}" maxlength="191">
            </div>

            <div class="col-md-4">
                <label class="form-label">Assigned to</label>
                <select class="form-select form-select-sm" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) old('assigned_user_id', $organization?->assigned_user_id) === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="active" @selected(old('status', $organization?->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $organization?->status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer since</label>
                <input type="date" class="form-control form-control-sm" name="customer_since" value="{{ old('customer_since', $organization?->customer_since?->format('Y-m-d')) }}">
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_customer" value="1" id="is_customer" @checked((bool) old('is_customer', $organization?->is_customer))>
                    <label class="form-check-label" for="is_customer">Customer</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_prospect" value="1" id="is_prospect" @checked((bool) old('is_prospect', $organization?->is_prospect))>
                    <label class="form-check-label" for="is_prospect">Prospect</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Country</label>
                <select class="form-select form-select-sm" name="country_id">
                    <option value="">— None —</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) old('country_id', $organization?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input type="text" class="form-control form-control-sm" name="address_line1" value="{{ old('address_line1', $organization?->address_line1) }}" maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input type="text" class="form-control form-control-sm" name="city" value="{{ old('city', $organization?->city) }}" maxlength="100">
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control form-control-sm" name="description" rows="3" maxlength="5000">{{ old('description', $organization?->description) }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $organization ? 'Save changes' : 'Create organization' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ $organization ? route('crm.organizations.show', $organization) : route('crm.organizations.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection