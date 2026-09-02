@extends('layouts.standalone')

@section('title', $lead->displayName() . ' — AccumenAI')
@section('page_title', $lead->displayName())

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">{{ $lead->displayName() }}</h4>
        @if ($lead->status)
            <span class="badge" style="background-color: {{ $lead->status->color ?? '#6c757d' }}">{{ $lead->status->name }}</span>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.leads.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        @if (!$lead->converted_at)
            <form method="POST" action="{{ route('sales.leads.convert', $lead) }}" onsubmit="return confirm('Convert this lead to a quotation? This will create a new customer.')">
                @csrf
                <button class="btn btn-sm btn-success rounded-pill"><i class="bi bi-arrow-right-circle me-1"></i>Convert to Quotation</button>
            </form>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Contact</h6>
                <p class="mb-1"><strong>Phone:</strong> {{ $lead->phone ?? '—' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $lead->email ?? '—' }}</p>
            </div>
            <div class="col-md-6">
                <h6>Details</h6>
                <p class="mb-1"><strong>Source:</strong> {{ $lead->source?->name ?? '—' }}</p>
                <p class="mb-1"><strong>Value:</strong> {{ $lead->value_amount ? number_format($lead->value_amount, 2) : '—' }}</p>
                <p class="mb-1"><strong>Assigned To:</strong> {{ $lead->assignedUser ? $lead->assignedUser->first_name . ' ' . $lead->assignedUser->last_name : '—' }}</p>
                <p class="mb-1"><strong>Created:</strong> {{ $lead->created_at->format('Y-m-d H:i') }}</p>
                @if ($lead->converted_at)
                    <p class="mb-1"><strong>Converted:</strong> {{ $lead->converted_at->format('Y-m-d H:i') }}</p>
                @endif
            </div>
        </div>
        @if ($lead->interest_summary)
            <div class="mt-3">
                <h6>Interest Summary</h6>
                <p class="text-muted">{{ $lead->interest_summary }}</p>
            </div>
        @endif
    </div>
</div>

@if ($lead->organization)
<div class="card mb-4">
    <div class="card-body">
        <h6>Organization</h6>
        <p class="mb-0 fw-semibold">{{ $lead->organization->name }}</p>
        <p class="text-muted small mb-0">{{ $lead->organization->email }} {{ $lead->organization->phone ? '• ' . $lead->organization->phone : '' }}</p>
    </div>
</div>
@endif

@if ($lead->contact)
<div class="card mb-4">
    <div class="card-body">
        <h6>Linked Contact</h6>
        <p class="mb-0 fw-semibold">{{ $lead->contact->displayName() }}</p>
        <p class="text-muted small mb-0">{{ $lead->contact->email }} {{ $lead->contact->phone ? '• ' . $lead->contact->phone : '' }}</p>
    </div>
</div>
@endif
@endsection
