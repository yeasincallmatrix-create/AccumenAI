@extends('guardian.layout')

@section('title', mawa_e('guardian.fees_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ mawa_e('guardian.fees_title') }}</h1>
        <div class="small text-body-secondary">{{ $student->full_name }} · {{ $student->student_id }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('guardian.back_to_profile') }}</a>
</div>

@php $t = $ledger['totals']; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.billed') }}</div>
            <div class="h5 mb-0">{{ number_format((float) $t['billed'], 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.paid') }}</div>
            <div class="h5 mb-0 text-success">{{ number_format((float) $t['collected'], 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.waived') }}</div>
            <div class="h5 mb-0">{{ number_format((float) $t['waivedTotal'], 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.outstanding') }}</div>
            <div class="h5 mb-0 text-danger">{{ number_format((float) $t['outstanding'], 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.overdue') }}</div>
            <div class="h5 mb-0 text-warning">{{ number_format((float) $t['overdue'], 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-body-secondary">{{ mawa_e('guardian.invoice_count') }}</div>
            <div class="h5 mb-0">{{ count($ledger['invoices']) }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-receipt me-1"></i>{{ mawa_e('guardian.invoices') }}
    </div>
    <div class="card-body p-0">
        @if (empty($ledger['invoices']))
            <div class="p-3"><div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>{{ mawa_e('guardian.no_invoices') }}</div></div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="text-body-secondary">
                            <th>{{ mawa_e('guardian.invoice_no') }}</th>
                            <th>{{ mawa_e('guardian.date') }}</th>
                            <th class="text-end">{{ mawa_e('guardian.payable') }}</th>
                            <th class="text-end">{{ mawa_e('guardian.paid') }}</th>
                            <th class="text-end">{{ mawa_e('guardian.due') }}</th>
                            <th class="text-center">{{ mawa_e('guardian.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledger['invoices'] as $invoice)
                            <tr>
                                <td class="fw-semibold">{{ $invoice['invoice_number'] }}</td>
                                <td>{{ $invoice['created_at'] ? \Illuminate\Support\Carbon::parse($invoice['created_at'])->format('d M Y') : mawa_e('guardian.na') }}</td>
                                <td class="text-end">{{ number_format((float) $invoice['payable_amount'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $invoice['paid_amount'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $invoice['due_amount'], 2) }}</td>
                                <td class="text-center">
                                    <span class="badge text-bg-{{ $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'unpaid' ? 'danger' : 'warning') }}">{{ $invoice['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection