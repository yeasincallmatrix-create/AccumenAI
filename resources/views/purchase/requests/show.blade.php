@extends('layouts.standalone')

@section('title', 'Purchase Request ' . $purchaseRequest->request_number . ' — AccumenAI')
@section('page_title', 'Purchase Request ' . $purchaseRequest->request_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Purchase Request {{ $purchaseRequest->request_number }}</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm rounded-pill" href="{{ route('purchase.requests.index') }}"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @if ($purchaseRequest->isSubmitted())
            <form method="POST" action="{{ route('purchase.requests.approve', $purchaseRequest) }}" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm rounded-pill" type="submit"><i class="bi bi-check-lg me-1"></i>Approve</button>
            </form>
        @endif
        @if ($purchaseRequest->canConvert())
            <form method="POST" action="{{ route('purchase.requests.convert', $purchaseRequest) }}" class="d-inline">
                @csrf
                <button class="btn btn-primary btn-sm rounded-pill" type="submit"><i class="bi bi-arrow-right-circle me-1"></i>Convert to PO</button>
            </form>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><strong>Request Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Request Number</label>
                        <div class="fw-semibold">{{ $purchaseRequest->request_number }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Requester</label>
                        <div>{{ $purchaseRequest->requester?->first_name }} {{ $purchaseRequest->requester?->last_name }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Status</label>
                        <div>
                            @php $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','converted'=>'primary','rejected'=>'danger','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$purchaseRequest->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $purchaseRequest->status)) }}</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Request Date</label>
                        <div>{{ $purchaseRequest->request_date?->format('Y-m-d') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Required By</label>
                        <div>{{ $purchaseRequest->required_by_date?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-0">Warehouse</label>
                        <div>{{ $purchaseRequest->warehouse?->name ?? '—' }}</div>
                    </div>
                    @if ($purchaseRequest->justification)
                        <div class="col-12">
                            <label class="form-label text-muted mb-0">Justification</label>
                            <div>{{ $purchaseRequest->justification }}</div>
                        </div>
                    @endif
                    @if ($purchaseRequest->notes)
                        <div class="col-12">
                            <label class="form-label text-muted mb-0">Notes</label>
                            <div>{{ $purchaseRequest->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><strong>Line Items</strong></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th>Unit</th>
                            <th class="text-end">Est. Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseRequest->lines as $idx => $line)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="text-end">{{ number_format($line->quantity, 2) }}</td>
                                <td>{{ $line->unit ?? '—' }}</td>
                                <td class="text-end">{{ number_format($line->estimated_unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No line items.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="5" class="text-end fw-semibold">Estimated Total</td>
                            <td class="text-end fw-semibold">{{ number_format($purchaseRequest->estimated_total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Workflow</strong></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <strong>Created:</strong> {{ $purchaseRequest->created_at?->format('Y-m-d H:i') ?? '—' }}
                    </li>
                    @if ($purchaseRequest->approved_at)
                        <li class="mb-2">
                            <strong>Approved:</strong> {{ $purchaseRequest->approved_at?->format('Y-m-d H:i') }}
                        </li>
                    @endif
                    @if ($purchaseRequest->converted_at)
                        <li class="mb-2">
                            <strong>Converted:</strong> {{ $purchaseRequest->converted_at?->format('Y-m-d H:i') }}
                        </li>
                    @endif
                    @if ($purchaseRequest->convertedOrder)
                        <li class="mb-2">
                            <strong>PO:</strong>
                            <a href="{{ route('purchase.orders.show', $purchaseRequest->convertedOrder) }}">{{ $purchaseRequest->convertedOrder->order_number }}</a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
