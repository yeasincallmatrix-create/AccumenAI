@extends('layouts.institute')

@section('title', 'Exchange Rates - AccumenAI')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-arrow-left-right me-2"></i>Exchange Rates</h4>
        <p class="page-header-desc mb-0">Manage exchange rates between your available currencies.</p>
    </div>
    <a href="{{ route('settings.currency.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>Back to Currency Settings
    </a>
</div>

@if(session('status'))
    <div class="alert alert-success py-2">{{ session('status') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-plus-circle me-1"></i> Add Exchange Rate</div>
            </div>
            <form method="POST" action="{{ route('settings.currency.rates.store') }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="from_currency">From Currency *</label>
                        <select id="from_currency" name="from_currency" class="form-select form-select-sm" required>
                            @foreach($currencies as $c)
                                <option value="{{ $c->code }}" {{ old('from_currency') === $c->code ? 'selected' : '' }}>{{ $c->code }} - {{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('from_currency')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="to_currency">To Currency *</label>
                        <select id="to_currency" name="to_currency" class="form-select form-select-sm" required>
                            @foreach($currencies as $c)
                                <option value="{{ $c->code }}" {{ old('to_currency') === $c->code ? 'selected' : '' }}>{{ $c->code }} - {{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('to_currency')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold" for="rate">Rate *</label>
                        <input type="number" step="0.000001" min="0.000001" id="rate" name="rate" class="form-control form-control-sm" value="{{ old('rate') }}" required placeholder="e.g. 1.09">
                        @error('rate')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold" for="rate_date">Date *</label>
                        <input type="date" id="rate_date" name="rate_date" class="form-control form-control-sm" value="{{ old('rate_date', date('Y-m-d')) }}" required>
                        @error('rate_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Save Rate</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-table me-1"></i> Exchange Rate History</div>
            </div>
            @if($rates->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">No exchange rates recorded yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Rate</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rates as $rate)
                                <tr>
                                    <td>{{ $rate->fromCurrency->code }} ({{ $rate->fromCurrency->symbol }})</td>
                                    <td>{{ $rate->toCurrency->code }} ({{ $rate->toCurrency->symbol }})</td>
                                    <td class="fw-bold">{{ number_format($rate->rate, 6) }}</td>
                                    <td>{{ $rate->rate_date->format('Y-m-d') }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('settings.currency.rates.destroy', $rate) }}" class="d-inline" onsubmit="return confirm('Delete this rate?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
