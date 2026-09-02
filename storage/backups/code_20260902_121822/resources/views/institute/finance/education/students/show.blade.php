@extends('layouts.standalone')

@section('title', 'Student Finance — AccumenAI')
@section('page_title', 'Finance')

@section('content')

@php
    $totals = $ledger['totals'];
@endphp

<div class="standalone-heading">
    <h4>{{ $student->full_name }}</h4>
    <p>{{ $student->student_id }} &middot; {{ $student->phone ?? 'no phone' }} &middot; {{ $student->email ?? 'no email' }}</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('finance.education.students.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Student Finance</a>
        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person me-1"></i>Student Profile</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Billed</div>
            <div class="fs-5 fw-semibold">{{ number_format($totals['billed'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Collected</div>
            <div class="fs-5 fw-semibold text-success">{{ number_format($totals['collected'], 2) }}</div>
            <div class="small text-muted">net of refunds</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Outstanding</div>
            <div class="fs-5 fw-semibold {{ $totals['outstanding'] > 0 ? 'text-danger' : '' }}">{{ number_format($totals['outstanding'], 2) }}</div>
            <div class="small text-muted">overdue {{ number_format($totals['overdue'], 2) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Waived</div>
            <div class="fs-5 fw-semibold">{{ number_format($totals['waivedTotal'], 2) }}</div>
            <div class="small text-muted">{{ count($ledger['waivers']) }} approved waivers</div>
        </div>
    </div>
</div>

@if ($user->hasPermission('accounts.manage') && $enrollments->isNotEmpty())
    <div class="admin-card mb-3">
        <h6 class="card-title">Generate invoice</h6>
        <form method="POST" action="{{ route('finance.education.students.invoice', $student) }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label mb-1">Enrollment</label>
                    <select class="form-select form-select-sm" name="enrollment_id" required>
                        @foreach ($enrollments as $enrollment)
                            <option value="{{ $enrollment->id }}">{{ $enrollment->batch?->name }} — {{ $enrollment->course?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Fee structure <span class="text-muted">(auto if empty)</span></label>
                    <select class="form-select form-select-sm" name="fee_structure_id">
                        <option value="">Auto-resolve</option>
                        @foreach ($structures as $structure)
                            <option value="{{ $structure->id }}">{{ $structure->name }} ({{ number_format($structure->total(), 2) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Discount</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="discount" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Due date</label>
                    <input type="date" class="form-control form-control-sm" name="due_date" value="{{ \Illuminate\Support\Carbon::today()->toDateString() }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-receipt me-1"></i>Generate</button>
                </div>
            </div>
            <div class="form-check form-check-inline mt-2">
                <input class="form-check-input" type="checkbox" name="include_optional" value="1" id="includeOptional">
                <label class="form-check-label small" for="includeOptional">Include optional fee items</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="allow_duplicate" value="1" id="allowDuplicate">
                <label class="form-check-label small" for="allowDuplicate">Allow a second open invoice</label>
            </div>
        </form>
    </div>
@endif

@foreach ($ledger['invoices'] as $invoice)
    <div class="admin-card mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <h6 class="card-title mb-0">
                    {{ $invoice['invoice_number'] }}
                    <span class="badge text-bg-{{ $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'partial' ? 'warning' : ($invoice['status'] === 'cancelled' ? 'secondary' : 'danger')) }}">{{ $invoice['status'] }}</span>
                    @if ($invoice['is_recurring'] ?? false)
                        <span class="badge text-bg-info">Recurring</span>
                    @endif
                </h6>
                <div class="text-muted small">{{ str_replace('_', ' ', $invoice['invoice_type']) }} &middot; created {{ $invoice['created_at'] }} &middot; due {{ $invoice['due_date'] ?? '—' }}@if ($invoice['billing_period'] ?? null) &middot; period {{ $invoice['billing_period'] }}@endif</div>
            </div>
            <div class="d-flex gap-3 text-end">
                <div><div class="small text-muted">Payable</div><div class="fw-semibold">{{ number_format($invoice['payable_amount'], 2) }}</div></div>
                <div><div class="small text-muted">Paid</div><div class="fw-semibold text-success">{{ number_format($invoice['paid_amount'], 2) }}</div></div>
                <div><div class="small text-muted">Due</div><div class="fw-semibold {{ $invoice['due_amount'] > 0 ? 'text-danger' : '' }}">{{ number_format($invoice['due_amount'], 2) }}</div></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Item</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice['items'] as $item)
                                <tr>
                                    <td>{{ $item['description'] }}@if ($item['fee_head']) <span class="badge text-bg-light text-muted ms-1">{{ $item['fee_head'] }}</span>@endif</td>
                                    <td class="text-end">{{ number_format($item['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            @if ($invoice['discount'] > 0)
                                <tr>
                                    <td class="text-muted">Discount / waiver</td>
                                    <td class="text-end text-danger">-{{ number_format($invoice['discount'], 2) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Installment</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th>Due</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice['installments'] as $installment)
                                <tr>
                                    <td>#{{ $installment['no'] }}</td>
                                    <td class="text-end">{{ number_format($installment['amount'], 2) }}</td>
                                    <td class="text-end">{{ number_format($installment['paid'], 2) }}</td>
                                    <td>{{ $installment['due_date'] }}</td>
                                    <td><span class="badge text-bg-{{ $installment['status'] === 'paid' ? 'success' : ($installment['status'] === 'overdue' ? 'danger' : 'secondary') }}">{{ $installment['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No installments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Payment</th><th class="text-end">Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice['payments'] as $payment)
                                <tr>
                                    <td>
                                        @if ($payment['receipt_number'] ?? null)
                                            <a href="{{ route('finance.education.receipt', $payment['id']) }}" class="text-decoration-none" target="_blank">{{ $payment['receipt_number'] }}</a>
                                        @else
                                            #{{ $payment['id'] }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($payment['amount'], 2) }}</td>
                                    <td>{{ $payment['method'] }}</td>
                                    <td>{{ $payment['paid_at'] }}</td>
                                    <td>
                                        @if ($payment['receipt_number'] ?? null)
                                            <a href="{{ route('finance.education.receipt', $payment['id']) }}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-receipt"></i></a>
                                        @endif
                                        @if ($payment['reversed'])
                                            <span class="badge text-bg-secondary">refunded</span>
                                        @elseif ($user->hasPermission('journals.reverse'))
                                            <form method="POST" action="{{ route('finance.education.payments.reverse', $payment['id']) }}" class="d-inline" onsubmit="return confirm('Reverse this payment and its receipt journal?')">
                                                @csrf
                                                <input type="hidden" name="reason" value="Refund requested">
                                                <button class="btn btn-sm btn-outline-danger">Refund</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No payments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($invoice['due_amount'] > 0 && $invoice['status'] !== 'cancelled')
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        @if ($user->hasPermission('accounts.manage'))
                            <form method="POST" action="{{ route('finance.education.students.payments', $student) }}" class="d-flex gap-1 align-items-end flex-wrap">
                                @csrf
                                <input type="hidden" name="invoice_id" value="{{ $invoice['id'] }}">
                                <div>
                                    <input type="number" step="0.01" min="0.01" max="{{ $invoice['due_amount'] }}" class="form-control form-control-sm" name="amount" placeholder="Amount" required>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="payment_method" required>
                                        @forelse ($paymentMethods as $pm)
                                            <option value="{{ strtolower($pm->name) }}">{{ $pm->name }}</option>
                                        @empty
                                            <option value="cash">Cash</option>
                                            <option value="bkash">bKash</option>
                                            <option value="nagad">Nagad</option>
                                            <option value="bank">Bank</option>
                                            <option value="card">Card</option>
                                            <option value="other">Other</option>
                                        @endforelse
                                    </select>
                                </div>
                                @if ($invoice['installments'])
                                    <div>
                                        <select class="form-select form-select-sm" name="installment_id">
                                            <option value="">Any installment</option>
                                            @foreach ($invoice['installments'] as $installment)
                                                @if ($installment['status'] !== 'paid')
                                                    <option value="{{ $installment['id'] }}">#{{ $installment['no'] }} ({{ $installment['due_date'] }})</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-cash-coin me-1"></i>Record payment</button>
                            </form>
                        @endif
                        @if ($user->hasPermission('journals.reverse'))
                            <form method="POST" action="{{ route('finance.education.invoices.waive', $invoice['id']) }}" class="d-flex gap-1 align-items-end flex-wrap" onsubmit="return confirm('Approve this waiver? It will adjust the sale journal.')">
                                @csrf
                                <div>
                                    <input type="number" step="0.01" min="0.01" max="{{ $invoice['due_amount'] }}" class="form-control form-control-sm" name="amount" placeholder="Waive amount" required>
                                </div>
                                <div>
                                    <input type="text" class="form-control form-control-sm" name="reason" placeholder="Reason" maxlength="500">
                                </div>
                                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-percent me-1"></i>Waive</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endforeach

@forelse ($ledger['invoices'] as $invoice)
@empty
    <div class="admin-card">
        <div class="text-center text-muted py-4">No invoices for this student yet.</div>
    </div>
@endforelse

@if ($ledger['waivers'])
    <div class="admin-card mb-3">
        <h6 class="card-title">Approved waivers</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Amount</th><th>Reason</th><th>Approved by</th><th>Approved at</th></tr>
                </thead>
                <tbody>
                    @foreach ($ledger['waivers'] as $waiver)
                        <tr>
                            <td class="fw-semibold">{{ number_format($waiver['amount'], 2) }}</td>
                            <td>{{ $waiver['reason'] ?? '—' }}</td>
                            <td>{{ $waiver['waived_by'] }}</td>
                            <td>{{ $waiver['waived_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection