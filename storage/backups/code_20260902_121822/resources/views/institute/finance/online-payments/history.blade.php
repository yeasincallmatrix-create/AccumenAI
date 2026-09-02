@extends('layouts.institute')

@section('title', 'Online Payment History — ' . ($institute->name ?? 'AccumenAI'))

@section('content')
<div class="page-header mb-3">
    <h4 class="page-header-title"><i class="bi bi-clock-history me-1"></i>Online Payment History</h4>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Invoice</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Gateway</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td>{{ $attempt->id }}</td>
                            <td>{{ $attempt->invoice?->invoice_number ?? '-' }}</td>
                            <td>{{ $attempt->student?->first_name }} {{ $attempt->student?->last_name }}</td>
                            <td>{{ number_format((float) $attempt->amount, 2) }}</td>
                            <td>{{ $attempt->gateway?->name ?? '-' }}</td>
                            <td><code>{{ $attempt->gateway_reference ?? '-' }}</code></td>
                            <td>
                                @php $colors = ['pending'=>'secondary','processing'=>'info','paid'=>'success','failed'=>'danger','cancelled'=>'warning','expired'=>'secondary']; @endphp
                                <span class="badge bg-{{ $colors[$attempt->status] ?? 'secondary' }}">{{ ucfirst($attempt->status) }}</span>
                            </td>
                            <td>{{ $attempt->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No online payment attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($attempts->hasPages())
        <div class="card-footer">{{ $attempts->links() }}</div>
    @endif
</div>
@endsection
