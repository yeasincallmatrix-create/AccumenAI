@extends('layouts.standalone')

@section('title', $contact ? 'Edit Contact — AccumenAI' : 'New Contact — AccumenAI')
@section('page_title', $contact ? 'Edit Contact' : 'New Contact')

@section('content')

<div class="standalone-heading">
    <h4>{{ $contact ? 'Edit Contact' : 'New Contact' }}</h4>
    <p>Contact details are institute-scoped. Duplicate emails are blocked automatically.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $contact ? route('crm.contacts.update', $contact) : route('crm.contacts.store') }}">
        @csrf
        @if ($contact) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Salutation</label>
                <input type="text" class="form-control form-control-sm" name="salutation" value="{{ old('salutation', $contact?->salutation) }}" maxlength="20">
            </div>
            <div class="col-md-5">
                <label class="form-label">First name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="first_name" value="{{ old('first_name', $contact?->first_name) }}" required maxlength="120">
            </div>
            <div class="col-md-5">
                <label class="form-label">Last name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="last_name" value="{{ old('last_name', $contact?->last_name) }}" required maxlength="120">
            </div>

            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-sm" name="email" value="{{ old('email', $contact?->email) }}" maxlength="191">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'crm_contact_phone', 'value' => old('phone', $contact?->phone)])
            </div>
            <div class="col-md-4">
                <label class="form-label">WhatsApp</label>
                @include('partials.phone', ['name' => 'whatsapp', 'id' => 'crm_contact_whatsapp', 'value' => old('whatsapp', $contact?->whatsapp)])
            </div>

            <div class="col-md-4">
                <label class="form-label">Contact type</label>
                <select class="form-select form-select-sm" name="contact_type_id">
                    <option value="">— None —</option>
                    @foreach ($contactTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) old('contact_type_id', $contact?->contact_type_id) === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Organization</label>
                <select class="form-select form-select-sm" name="organization_id">
                    <option value="">— None —</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((string) old('organization_id', $contact?->organization_id) === (string) $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Designation</label>
                <input type="text" class="form-control form-control-sm" name="designation" value="{{ old('designation', $contact?->designation) }}" maxlength="120">
            </div>

            <div class="col-md-4">
                <label class="form-label">Lead source</label>
                <select class="form-select form-select-sm" name="source_id">
                    <option value="">— None —</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected((string) old('source_id', $contact?->source_id) === (string) $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Assigned to</label>
                <select class="form-select form-select-sm" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) old('assigned_user_id', $contact?->assigned_user_id) === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="active" @selected(old('status', $contact?->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $contact?->status) === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_customer" value="1" id="is_customer" @checked((bool) old('is_customer', $contact?->is_customer))>
                    <label class="form-check-label" for="is_customer">Customer</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_prospect" value="1" id="is_prospect" @checked((bool) old('is_prospect', $contact?->is_prospect))>
                    <label class="form-check-label" for="is_prospect">Prospect</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer since</label>
                <input type="date" class="form-control form-control-sm" name="customer_since" value="{{ old('customer_since', $contact?->customer_since?->format('Y-m-d')) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Country</label>
                <select class="form-select form-select-sm" name="country_id">
                    <option value="">— None —</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) old('country_id', $contact?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input type="text" class="form-control form-control-sm" name="address_line1" value="{{ old('address_line1', $contact?->address_line1) }}" maxlength="255">
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="3" maxlength="5000">{{ old('notes', $contact?->notes) }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $contact ? 'Save changes' : 'Create contact' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ $contact ? route('crm.contacts.show', $contact) : route('crm.contacts.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection