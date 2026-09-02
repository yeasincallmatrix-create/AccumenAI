@extends('layouts.institute')

@section('title', 'Online Payment — ' . ($institute->name ?? 'AccumenAI'))

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-header-title"><i class="bi bi-credit-card me-1"></i>Online Payment</h4>
        <p class="text-muted small mb-0">Pay invoice online via a secure payment gateway.</p>
    </div>
    <div>
        <a href="{{ route('online-payments.history') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>Payment History</a>
    </div>
</div>

@if ($invoice === null)
    <div class="alert alert-warning">Invoice not found or not available for online payment.</div>
@else
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Invoice {{ $invoice->invoice_number }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Student</th><td>{{ $invoice->student?->first_name }} {{ $invoice->student?->last_name }}</td></tr>
                        <tr><th>Payable</th><td>{{ number_format((float) $invoice->payable_amount, 2) }} {{ $invoice->currency?->code ?? '' }}</td></tr>
                        <tr><th>Paid</th><td>{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
                        <tr><th>Due</th><td class="fw-bold text-danger">{{ number_format((float) $invoice->due_amount, 2) }}</td></tr>
                        <tr><th>Status</th><td><span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'secondary') }}">{{ ucfirst($invoice->status) }}</span></td></tr>
                    </table>
                </div>
            </div>

            @if ($invoice->installments->count() > 0)
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">Installments</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>#</th><th>Amount</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach ($invoice->installments as $inst)
                                <tr>
                                    <td>{{ $inst->id }}</td>
                                    <td>{{ number_format((float) $inst->amount, 2) }}</td>
                                    <td>{{ number_format((float) $inst->paid_amount, 2) }}</td>
                                    <td>{{ number_format((float) $inst->amount - (float) $inst->paid_amount, 2) }}</td>
                                    <td><span class="badge bg-{{ $inst->status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($inst->status) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Pay Now</h6></div>
                <div class="card-body">
                    @if ($gateways->isEmpty())
                        <div class="alert alert-info mb-0">No payment gateways are enabled for this institute.</div>
                    @else
                        <form method="POST" action="{{ route('online-payments.process') }}">
                            @csrf
                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                            <input type="hidden" name="installment_id" value="">

                            <div class="mb-3">
                                <label class="form-label">Amount ({{ $invoice->currency?->code ?? 'BDT' }})</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                                    max="{{ number_format((float) $invoice->due_amount, 2, '.', '') }}"
                                    value="{{ number_format((float) $invoice->due_amount, 2, '.', '') }}" required>
                                @error('amount')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pay via</label>
                                <select name="gateway_id" class="form-select" required>
                                    @foreach ($gateways as $gw)
                                        <option value="{{ $gw->gateway_id }}">{{ $gw->gateway->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-lock me-1"></i>Proceed to Payment</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
