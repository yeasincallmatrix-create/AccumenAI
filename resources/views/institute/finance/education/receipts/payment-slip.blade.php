@extends('layouts.standalone')

@section('title', 'Payment Receipt — AccumenAI')
@section('page_title', 'Payment Receipt')

@section('content')
@php
    $invoice = $payment->invoice;
    $student = $payment->student;
    $items = $invoice?->items ?? collect();
    $enrollment = $invoice?->enrollment;
    $course = $enrollment?->course;
    $batch = $enrollment?->batch;
    $academicYear = $batch?->academicYear;
@endphp

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white !important; }
        .receipt-box { box-shadow: none !important; border: 1px solid #000 !important; max-width: 100% !important; }
    }
</style>

<div class="no-print mb-3">
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Receipt</button>
</div>

<div class="receipt-box card mx-auto" style="max-width: 800px;">
    <div class="card-body p-4">

        {{-- Institute Header — global tenant logo via $institute->logo_url --}}
        <div class="text-center mb-4 border-bottom pb-3">
            <img src="{{ $institute->logo_url }}" alt="{{ $institute->name ?? 'Institute' }} Logo" style="max-height:60px; margin-bottom:10px; object-fit:contain;" class="mb-2">
            <h4 class="mb-0 fw-bold">{{ $institute->name ?? 'Institute' }}</h4>
            <div class="text-muted small">
                @if ($institute->address ?? null) {{ $institute->address }}<br>@endif
                @if ($institute->phone ?? null) Phone: {{ $institute->phone }} @endif
                @if ($institute->email ?? null) | Email: {{ $institute->email }} @endif
                @if ($institute->website ?? null) | {{ $institute->website }} @endif
            </div>
        </div>

        {{-- Receipt Title --}}
        <div class="text-center mb-3">
            <h5 class="fw-bold text-uppercase">Payment Receipt</h5>
        </div>

        {{-- Receipt + Student Info --}}
        <div class="row mb-3">
            <div class="col-6">
                <div class="small text-muted mb-1">Receipt Information</div>
                <div><strong>Receipt No:</strong> {{ $payment->receipt_number ?? '—' }}</div>
                <div><strong>Payment Date:</strong> {{ $payment->paid_at?->format('d M Y H:i') ?? '—' }}</div>
                <div><strong>Payment Method:</strong> {{ ucfirst($payment->payment_method) }}</div>
                @if ($payment->transaction_id ?? null)
                    <div><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                @endif
                @if ($invoice?->invoice_number ?? null)
                    <div><strong>Invoice:</strong> {{ $invoice->invoice_number }}</div>
                @endif
            </div>
            <div class="col-6">
                <div class="small text-muted mb-1">Student Information</div>
                <div><strong>Name:</strong> {{ $student->full_name ?? '—' }}</div>
                <div><strong>Reg No:</strong> {{ $student->reg_no ?? '—' }}</div>
                <div><strong>Student ID:</strong> {{ $student->student_id ?? '—' }}</div>
                @if ($course ?? null)
                    <div><strong>Course:</strong> {{ $course->name }}</div>
                @endif
                @if ($batch ?? null)
                    <div><strong>Batch:</strong> {{ $batch->name }}</div>
                @endif
                @if ($academicYear ?? null)
                    <div><strong>Session:</strong> {{ $academicYear->name }}</div>
                @endif
                @if ($student->phone ?? null)
                    <div><strong>Phone:</strong> {{ $student->phone }}</div>
                @endif
            </div>
        </div>

        {{-- Fee Breakdown --}}
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fee Head</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->feeHead?->name ?? 'Fee' }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="row mb-3">
            <div class="col-6 offset-6">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>Total Payable</td>
                            <td class="text-end fw-semibold">{{ number_format((float) ($invoice?->payable_amount ?? 0), 2) }}</td>
                        </tr>
                        @if ((float) ($invoice?->discount ?? 0) > 0)
                            <tr>
                                <td class="text-success">Discount / Waiver</td>
                                <td class="text-end text-success">-{{ number_format((float) $invoice->discount, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="table-light">
                            <td class="fw-bold">Amount Paid</td>
                            <td class="text-end fw-bold fs-5">{{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Remaining Due</td>
                            <td class="text-end {{ ($invoice?->due_amount ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                {{ number_format((float) ($invoice?->due_amount ?? 0), 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-top pt-3 mt-3">
            <div class="row">
                <div class="col-6">
                    <div class="small text-muted">Collected By</div>
                    <div>{{ $payment->receivedBy?->full_name ?? '—' }}</div>
                </div>
                <div class="col-6 text-end">
                    <div class="small text-muted">Student / Guardian Signature</div>
                    <div style="border-bottom: 1px solid #999; width: 200px; display: inline-block; margin-top: 30px;"></div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                This receipt is system generated and valid without a stamp/signature where applicable.
                <br>Receipt No: {{ $payment->receipt_number ?? '—' }} | {{ $institute->name }}
            </small>
        </div>

    </div>
</div>
@endsection
