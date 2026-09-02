@extends('layouts.standalone')

@section('title', 'Fee Collection — AccumenAI')
@section('page_title', 'Fee Collection')

@section('content')

@if (!isset($student))
    {{-- Search Screen --}}
    <div class="standalone-heading">
        <h4>Fee Collection</h4>
        <p class="text-muted">Search for a student to view and collect outstanding fees.</p>
    </div>

    <div class="admin-card">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label mb-1">Search Student</label>
                <input type="text" name="q" class="form-control" placeholder="Student name, ID, phone, or registration number" value="{{ request('q') }}" autofocus>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            </div>
        </form>
    </div>

    @if (request('q'))
        @php
            $searchResults = \App\Models\Student::query()
                ->withoutGlobalScopes()
                ->where(function ($q) {
                    $term = '%' . request('q') . '%';
                    $q->where('first_name', 'like', $term)
                      ->orWhere('last_name', 'like', $term)
                      ->orWhere('student_id_number', 'like', $term)
                      ->orWhere('reg_no', 'like', $term)
                      ->orWhere('phone', 'like', $term);
                })
                ->where('admission_status', 'enrolled')
                ->where('status', 'active')
                ->limit(20)
                ->get();
        @endphp

        <div class="admin-card mt-3">
            @if ($searchResults->isEmpty())
                <div class="text-center text-muted py-4">No students found matching "{{ request('q') }}".</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID</th>
                                <th>Reg No</th>
                                <th>Phone</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($searchResults as $s)
                                <tr>
                                    <td class="fw-semibold">{{ $s->full_name }}</td>
                                    <td>{{ $s->student_id }}</td>
                                    <td>{{ $s->reg_no ?? '—' }}</td>
                                    <td>{{ $s->phone ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('finance.education.fee-collection.student', $s) }}" class="btn btn-sm btn-primary rounded-pill">
                                            <i class="bi bi-arrow-right"></i> Collect
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

@else
    {{-- Collection Screen --}}
    @php
        $totals = [
            'previous_due' => $previous_due ?? 0,
            'current_month' => $current_month ?? 0,
            'overdue' => $overdue ?? 0,
            'total_outstanding' => $total_outstanding ?? 0,
        ];
        $paymentMethodsList = $paymentMethods ?? collect();
    @endphp

    <div class="standalone-heading">
        <h4>Fee Collection</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('finance.education.fee-collection') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Search</a>
        </div>
    </div>

    {{-- Student Information --}}
    <div class="admin-card mb-3">
        <div class="row g-3">
            <div class="col-md-8">
                <h6 class="card-title">Student Information</h6>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="small text-muted">Name</div>
                        <div class="fw-semibold">{{ $student['full_name'] }}</div>
                    </div>
                    <div class="col-sm-3">
                        <div class="small text-muted">Student ID</div>
                        <div>{{ $student['student_id_number'] }}</div>
                    </div>
                    <div class="col-sm-3">
                        <div class="small text-muted">Reg No</div>
                        <div>{{ $student['reg_no'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="small text-muted">Phone</div>
                        <div>{{ $student['phone'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="small text-muted">Email</div>
                        <div>{{ $student['email'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="small text-muted">Status</div>
                        <div><span class="badge text-bg-{{ $student['status'] === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($student['status']) }}</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <h6 class="card-title">Due Summary</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="small text-muted">Previous Due</div>
                        <div class="fs-5 fw-semibold text-danger">{{ number_format($totals['previous_due'], 2) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Current Month</div>
                        <div class="fs-5 fw-semibold">{{ number_format($totals['current_month'], 2) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Overdue</div>
                        <div class="fs-6 fw-semibold text-danger">{{ number_format($totals['overdue'], 2) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Total Outstanding</div>
                        <div class="fs-5 fw-bold {{ $totals['total_outstanding'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($totals['total_outstanding'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Outstanding Invoices --}}
    @if (isset($invoices) && count($invoices) > 0)
        <div class="admin-card mb-3">
            <h6 class="card-title">Outstanding Invoices</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th>Invoice #</th>
                            <th>Period</th>
                            <th>Type</th>
                            <th class="text-end">Payable</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $inv)
                            <tr>
                                <td>
                                    @if ($inv['due_amount'] > 0)
                                        <input type="checkbox" class="form-check-input invoice-check" value="{{ $inv['id'] }}" data-due="{{ $inv['due_amount'] }}">
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $inv['invoice_number'] }}</td>
                                <td>{{ $inv['billing_period'] ?? '—' }}</td>
                                <td>{{ str_replace('_', ' ', $inv['invoice_type']) }}</td>
                                <td class="text-end">{{ number_format($inv['payable_amount'], 2) }}</td>
                                <td class="text-end">{{ number_format($inv['paid_amount'], 2) }}</td>
                                <td class="text-end fw-semibold {{ $inv['due_amount'] > 0 ? 'text-danger' : '' }}">{{ number_format($inv['due_amount'], 2) }}</td>
                                <td><span class="badge text-bg-{{ $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partial' ? 'warning' : 'danger') }}">{{ $inv['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payment Form --}}
        <div class="admin-card mb-3">
            <h6 class="card-title">Record Payment</h6>
            <form method="POST" action="{{ route('finance.education.fee-collection.pay') }}" id="collectFeeForm">
                @csrf
                <input type="hidden" name="student_id" value="{{ $student['id'] }}">
                <div id="invoiceIdContainer"></div>

                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label mb-1">Amount ({{ $baseCurrency ?? 'USD' }})</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="paymentAmount" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select method</option>
                            @foreach ($paymentMethodsList as $method)
                                <option value="{{ strtolower($method->name) }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Transaction ID</label>
                        <input type="text" class="form-control" name="transaction_id" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Payment Date</label>
                        <input type="date" class="form-control" name="paid_at" value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success" id="payBtn" disabled>
                        <i class="bi bi-cash-coin me-1"></i>Record Payment
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="admin-card">
            <div class="text-center text-muted py-4">
                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                No outstanding fees for this student. All invoices are paid or cancelled.
            </div>
        </div>
    @endif

    @if (isset($invoices) && count($invoices) > 0)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checks = document.querySelectorAll('.invoice-check');
            const selectAll = document.getElementById('selectAll');
            const amountInput = document.getElementById('paymentAmount');
            const invoiceIdContainer = document.getElementById('invoiceIdContainer');
            const payBtn = document.getElementById('payBtn');

            function updateSelection() {
                const selected = Array.from(checks).filter(c => c.checked);
                const totalDue = selected.reduce((s, c) => s + parseFloat(c.dataset.due || 0), 0);
                amountInput.value = totalDue > 0 ? totalDue.toFixed(2) : '';

                invoiceIdContainer.innerHTML = '';
                selected.forEach(c => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'invoice_ids[]';
                    input.value = c.value;
                    invoiceIdContainer.appendChild(input);
                });

                payBtn.disabled = selected.length === 0;
            }

            checks.forEach(c => c.addEventListener('change', updateSelection));
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checks.forEach(c => c.checked = this.checked);
                    updateSelection();
                });
            }
        });
    </script>
    @endif

@endif

@endsection
