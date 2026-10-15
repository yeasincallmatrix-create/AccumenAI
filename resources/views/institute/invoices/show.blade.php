@extends('layouts.institute')

@section('title', 'Invoice '.$invoice->invoice_number.' — AccumenAI')

@section('content')

@php $isSales = $invoice->sales_order_id !== null; @endphp

<div class="standalone-heading">
    <h4>Invoice {{ $invoice->invoice_number }}</h4>
    <p>
        {{ $invoice->party?->name ?? $invoice->student?->name ?? 'Walk-in' }}
        @if ($invoice->invoice_type) · {{ str_replace('_', ' ', $invoice->invoice_type) }} @endif
        · <x-tdate :value="$invoice->created_at" fallback="Y-m-d" />
    </p>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        @if ($invoice->status !== 'cancelled' && (float) $invoice->paid_amount === 0)
            {{-- Unified cancel reuses the existing finance cancel route (finance.manage). --}}
            <form method="POST" action="{{ route('finance.invoices.cancel', $invoice) }}" class="d-inline" data-ajax-submit="1" data-confirm="Cancel this invoice? Its sale journal will be reversed.">
                @csrf
                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg me-1"></i>Cancel invoice</button>
            </form>
        @endif
        @if ($isSales && auth('institute_user')->user()?->hasPermission('sales.invoices.view'))
            <a href="{{ route('sales.invoices.show', $invoice) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>View in Sales</a>
        @elseif (! $isSales)
            <a href="{{ route('finance.invoices.show', $invoice) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>View in Finance</a>
        @endif
        <span class="badge text-bg-{{ $isSales ? 'primary' : 'secondary' }}">{{ $isSales ? 'Sales' : 'Finance' }}</span>
        <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : ($invoice->status === 'cancelled' ? 'secondary' : 'danger')) }}">{{ $invoice->status }}</span>
    </div>
</div>

<div class="row g-3 mb-3">
    @if ($invoice->party)
        <div class="col-lg-4 col-md-6">
            <div class="admin-card mb-0">
                <h6 class="card-title">Customer</h6>
                <p class="mb-0">{{ $invoice->party->name }}</p>
                @if ($invoice->party->phone)<p class="text-muted small mb-0">{{ $invoice->party->phone }}</p>@endif
                @if ($invoice->party->email)<p class="text-muted small mb-0">{{ $invoice->party->email }}</p>@endif
            </div>
        </div>
    @endif
    @if ($invoice->student_id && $invoice->student)
        <div class="col-lg-4 col-md-6">
            <div class="admin-card mb-0">
                <h6 class="card-title">Student</h6>
                <p class="mb-0">{{ $invoice->student->name }}</p>
                @if ($invoice->student->student_id ?? null)<p class="text-muted small mb-0">ID: {{ $invoice->student->student_id }}</p>@endif
            </div>
        </div>
    @endif
    @if ($invoice->sales_order_id && $invoice->salesOrder)
        <div class="col-lg-4 col-md-6">
            <div class="admin-card mb-0">
                <h6 class="card-title">Sales Order</h6>
                <p class="mb-0">
                    <a href="{{ route('sales.orders.show', $invoice->salesOrder) }}" class="text-decoration-none">{{ $invoice->salesOrder->order_number }}</a>
                </p>
                <p class="text-muted small mb-0">Status: {{ $invoice->salesOrder->status }}</p>
            </div>
        </div>
    @endif
    @if ($invoice->is_progressive)
        <div class="col-lg-4 col-md-6">
            <div class="admin-card mb-0">
                <h6 class="card-title">Progressive Billing</h6>
                @if ($invoice->milestone_name)<p class="mb-0">Milestone: {{ $invoice->milestone_name }}</p>@endif
                <p class="text-muted small mb-0">
                    Progress: {{ number_format((float) $invoice->progress_percentage, 2) }}%
                    @if ($invoice->is_final_progressive) · Final invoice @endif
                </p>
                <p class="text-muted small mb-0">Cumulative billed: {{ number_format((float) $invoice->cumulative_billed, 2) }}</p>
                @if ((float) $invoice->retention_amount > 0)
                    <p class="text-muted small mb-0">Retention: {{ number_format((float) $invoice->retention_amount, 2) }}</p>
                @endif
            </div>
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Items</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>
                                    {{ $item->description }}
                                    @if ($item->coa)
                                        <div class="text-muted small">{{ $item->coa->code }} — {{ $item->coa->name }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-end">Total</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $invoice->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Discount</td>
                            <td class="text-end">{{ number_format((float) $invoice->discount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Payable</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $invoice->payable_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Paid</td>
                            <td class="text-end">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Due</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format((float) $invoice->due_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($invoice->journal)
            <div class="admin-card">
                <h6 class="card-title">Ledger posting</h6>
                <p class="mb-0">
                    <a href="{{ route('finance.journals.show', $invoice->journal) }}" class="text-decoration-none">{{ $invoice->journal->journal_no }}</a>
                    <span class="badge text-bg-{{ $invoice->journal->status === 'posted' ? 'success' : 'warning' }} ms-1">{{ $invoice->journal->status }}</span>
                </p>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Payments</h6>
            @forelse ($invoice->payments as $payment)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>
                        <span class="badge text-bg-light border">{{ $payment->payment_method }}</span>
                        {{ number_format((float) $payment->amount, 2) }}
                        <span class="small text-muted"><x-tdate :value="$payment->paid_at" fallback="Y-m-d H:i" :datetime="true" /></span>
                    </span>
                    <span>
                        @if ($payment->journal?->status === 'posted')
                            <span class="badge text-bg-success">Posted</span>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-muted mb-0">No payments yet.</p>
            @endforelse
        </div>

        @if ($invoice->installments->isNotEmpty())
            <div class="admin-card">
                <h6 class="card-title">Installments</h6>
                @foreach ($invoice->installments as $installment)
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span>#{{ $installment->installment_no }} — {{ number_format((float) $installment->amount, 2) }}</span>
                        <span>
                            <span class="badge text-bg-{{ $installment->status === 'paid' ? 'success' : ($installment->status === 'overdue' ? 'danger' : 'secondary') }}">{{ $installment->status }}</span>
                            <span class="small text-muted ms-1">due {{ $installment->due_date }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="admin-card">
                @include('documents._panel', ['entityType' => 'invoice', 'entityId' => $invoice->id])
            </div>
        </div>
    </div>
@endif

@endsection
