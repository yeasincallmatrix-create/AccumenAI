@extends('layouts.standalone')

@section('title', $lead ? 'Edit Lead — AccumenAI' : 'New Lead — AccumenAI')
@section('page_title', $lead ? 'Edit Lead' : 'New Lead')

@section('content')

<div class="standalone-heading">
    <h4>{{ $lead ? 'Edit Lead' : 'New Lead' }}</h4>
    <p>Lead details are institute-scoped. Duplicate emails are blocked automatically.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $lead ? route('crm.leads.update', $lead) : route('crm.leads.store') }}">
        @csrf
        @if ($lead) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">First name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="first_name" value="{{ old('first_name', $lead?->first_name) }}" required maxlength="120">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="last_name" value="{{ old('last_name', $lead?->last_name) }}" required maxlength="120">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'crm_lead_phone', 'value' => old('phone', $lead?->phone)])
            </div>

            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-sm" name="email" value="{{ old('email', $lead?->email) }}" maxlength="191">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status_id">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected((string) old('status_id', $lead?->status_id) === (string) $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Source</label>
                <select class="form-select form-select-sm" name="source_id">
                    <option value="">— None —</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected((string) old('source_id', $lead?->source_id) === (string) $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Value (est.)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="value_amount" value="{{ old('value_amount', $lead?->value_amount) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Linked contact</label>
                <select class="form-select form-select-sm" name="contact_id">
                    <option value="">— None —</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}" @selected((string) old('contact_id', $lead?->contact_id) === (string) $contact->id)>{{ $contact->first_name }} {{ $contact->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Organization</label>
                <select class="form-select form-select-sm" name="organization_id">
                    <option value="">— None —</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((string) old('organization_id', $lead?->organization_id) === (string) $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Assigned to</label>
                <select class="form-select form-select-sm" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) old('assigned_user_id', $lead?->assigned_user_id) === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Interest summary</label>
                <textarea class="form-control form-control-sm" name="interest_summary" rows="3" maxlength="5000">{{ old('interest_summary', $lead?->interest_summary) }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $lead ? 'Save changes' : 'Create lead' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ $lead ? route('crm.leads.show', $lead) : route('crm.leads.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection