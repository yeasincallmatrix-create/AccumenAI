@extends('layouts.standalone')

@section('title', 'Exchange Rates — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Exchange Rates</h4>
    <p>Manage foreign-currency exchange rates. Rates are tenant/branch scoped.</p>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card mb-3">
    <div class="p-3 border-bottom">
        <h6 class="mb-2">Add Exchange Rate</h6>
        <form method="POST" action="{{ route('finance.exchange-rates.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">From Currency</label>
                <select class="form-select form-select-sm" name="from_currency_id" required>
                    <option value="">Select</option>
                    @foreach ($currencies as $cur)
                        <option value="{{ $cur->id }}">{{ $cur->code }} — {{ $cur->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">To Currency</label>
                <select class="form-select form-select-sm" name="to_currency_id" required>
                    <option value="">Select</option>
                    @foreach ($currencies as $cur)
                        <option value="{{ $cur->id }}">{{ $cur->code }} — {{ $cur->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Buy Rate</label>
                <input type="number" step="0.000001" class="form-control form-control-sm" name="buy_rate" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sell Rate</label>
                <input type="number" step="0.000001" class="form-control form-control-sm" name="sell_rate" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Effective Date</label>
                <input type="date" class="form-control form-control-sm" name="effective_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-circle"></i> Add Rate</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th>Buy</th>
                    <th>Sell</th>
                    <th>Effective</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr>
                        <td>{{ $rate->from_currency->code ?? 'N/A' }}</td>
                        <td>{{ $rate->to_currency->code ?? 'N/A' }}</td>
                        <td>{{ number_format((float) $rate->buy_rate, 8) }}</td>
                        <td>{{ number_format((float) $rate->sell_rate, 8) }}</td>
                        <td>{{ $rate->effective_date?->format('Y-m-d') }}</td>
                        <td><span class="badge text-bg-light border">{{ $rate->source ?? 'manual' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('finance.exchange-rates.destroy', $rate->id) }}" class="d-inline" onsubmit="return confirm('Delete this rate?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">No exchange rates configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rates->hasPages())
        <div class="p-2 border-top">{{ $rates->links() }}</div>
    @endif
</div>

@endsection
