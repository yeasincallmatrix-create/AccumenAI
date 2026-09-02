@extends('layouts.standalone')

@section('title', 'FX Revaluation — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>FX Revaluation</h4>
    <p>Period-end foreign-currency revaluation. Posts adjustment journals for unrealized FX gains/losses.</p>
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
        <h6 class="mb-2">Run Revaluation</h6>
        <form method="POST" action="{{ route('finance.fx-revaluations.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label">As-of Date</label>
                <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Currency (optional, leave blank for all)</label>
                <select class="form-select form-select-sm" name="currency_id">
                    <option value="">All foreign currencies</option>
                    @foreach (\App\Models\Currency::where('is_active', true)->orderBy('code')->get() as $cur)
                        <option value="{{ $cur->id }}">{{ $cur->code }} — {{ $cur->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-warning btn-sm w-100"><i class="bi bi-calculator"></i> Run Revaluation</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Currency</th>
                    <th>Closing Rate</th>
                    <th>Carrying</th>
                    <th>Revalued</th>
                    <th>Difference</th>
                    <th>Journal</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($revaluations as $fxr)
                    <tr>
                        <td>{{ $fxr->as_of_date?->format('Y-m-d') }}</td>
                        <td>{{ $fxr->currency->code ?? 'N/A' }}</td>
                        <td>{{ number_format((float) $fxr->closing_rate, 8) }}</td>
                        <td>{{ number_format((float) $fxr->carrying_value, 4) }}</td>
                        <td>{{ number_format((float) $fxr->revalued_value, 4) }}</td>
                        <td class="{{ $fxr->difference >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format((float) $fxr->difference, 4) }}
                        </td>
                        <td>
                            @if ($fxr->journal)
                                <a href="{{ route('finance.journals.show', $fxr->journal_id) }}">{{ $fxr->journal->journal_no }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $fxr->status === 'posted' ? 'success' : 'secondary' }}">{{ $fxr->status }}</span>
                        </td>
                        <td>
                            @if ($fxr->status === 'posted' && $fxr->journal_id)
                                <form method="POST" action="{{ route('finance.fx-revaluations.reverse', $fxr->id) }}" class="d-inline" onsubmit="return confirm('Reverse this revaluation?')">
                                    @csrf
                                    <input type="hidden" name="reason" value="Manual reversal from UI">
                                    <button class="btn btn-outline-warning btn-sm" type="submit" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">No revaluations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($revaluations->hasPages())
        <div class="p-2 border-top">{{ $revaluations->links() }}</div>
    @endif
</div>

@endsection
