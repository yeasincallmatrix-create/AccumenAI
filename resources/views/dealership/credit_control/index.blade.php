@extends('layouts.standalone')

@section('title', 'Credit Control — AccumenAI')
@section('page_title', 'Credit Control')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-shield-check me-2"></i>ক্রেডিট কন্ট্রোল (Credit Control)</h4>
</div>

<div class="admin-card card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Customer</th><th>Limit</th><th>Overdue Block (days)</th><th>Blocked</th><th>Reviewed</th><th></th></tr></thead>
            <tbody>
                @forelse($limits as $row)
                    <tr>
                        <td>{{ $row->customer_id }}</td>
                        <td>{{ number_format($row->credit_limit, 2) }}</td>
                        <td>{{ $row->overdue_days_block }}</td>
                        <td>{{ $row->is_blocked ? 'Yes' : 'No' }}</td>
                        <td>{{ $row->last_reviewed_at ?? '—' }}</td>
                        <td class="d-flex gap-1">
                            <form method="POST" action="{{ route('dealership.credit_control.block', $row->customer_id) }}">@csrf<button class="btn btn-sm btn-danger" type="submit">Block</button></form>
                            <form method="POST" action="{{ route('dealership.credit_control.unblock', $row->customer_id) }}">@csrf<button class="btn btn-sm btn-success" type="submit">Unblock</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">কোনো লিমিট নেই (No credit limits yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $limits->links() }}
@endsection
