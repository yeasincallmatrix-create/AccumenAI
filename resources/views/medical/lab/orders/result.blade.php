@extends('layouts.institute')

@section('title', 'Enter Results — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Enter Results — {{ clinical_no($order->order_number) }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.lab.orders.show', $order) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Numeric values are auto-interpreted against each test's normal range (normal / abnormal / critical). Leave a row empty to keep it pending.
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.lab.orders.result', $order) }}" method="POST">
            @csrf
            @foreach($order->results as $result)
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        {{ $result->labTest->display_name ?? 'Test #'.$result->lab_test_id }}
                        @if($result->labTest->unit)<span class="text-muted">({{ $result->labTest->unit }})</span>@endif
                    </h6>
                    <small class="text-muted">Reference: {{ $result->normal_range ?? $result->labTest->normal_range ?? '—' }}</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="value-{{ $result->id }}">Result Value</label>
                                <input type="text" id="value-{{ $result->id }}"
                                       name="results[{{ $result->id }}][result_value]"
                                       class="form-control"
                                       value="{{ old('results.'.$result->id.'.result_value', $result->result_value) }}"
                                       placeholder="e.g. 95">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="text-{{ $result->id }}">Result Text</label>
                                <input type="text" id="text-{{ $result->id }}"
                                       name="results[{{ $result->id }}][result_text]"
                                       class="form-control"
                                       value="{{ old('results.'.$result->id.'.result_text', $result->result_text) }}"
                                       placeholder="e.g. Negative">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="comments-{{ $result->id }}">Comments</label>
                                <input type="text" id="comments-{{ $result->id }}"
                                       name="results[{{ $result->id }}][comments]"
                                       class="form-control"
                                       value="{{ old('results.'.$result->id.'.comments', $result->comments) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Results & Complete Order
            </button>
            <a href="{{ route('medical.lab.orders.show', $order) }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
