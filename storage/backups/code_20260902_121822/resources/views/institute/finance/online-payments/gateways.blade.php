@extends('layouts.institute')

@section('title', 'Payment Gateways — ' . ($institute->name ?? 'AccumenAI'))

@section('content')
<div class="page-header mb-3">
    <h4 class="page-header-title"><i class="bi bi-plug me-1"></i>Payment Gateways</h4>
    <p class="text-muted small mb-0">Enable and configure online payment gateways for this institute.</p>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Gateway</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allGateways as $gateway)
                    <tr>
                        <td>
                            <strong>{{ $gateway->name }}</strong>
                            @if ($gateway->description)
                                <br><small class="text-muted">{{ $gateway->description }}</small>
                            @endif
                        </td>
                        <td>
                            @if (in_array($gateway->id, $enabledGatewayIds))
                                <span class="badge bg-success">Enabled</span>
                            @else
                                <span class="badge bg-secondary">Disabled</span>
                            @endif
                        </td>
                        <td>
                            @if (in_array($gateway->id, $enabledGatewayIds))
                                <form method="POST" action="{{ route('finance.online-payments.disable', $gateway) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Disable this gateway?')">Disable</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('finance.online-payments.enable', $gateway) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Enable</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">No gateways available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('finance.online-payments.attempts') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-ul me-1"></i>All Attempts</a>
</div>
@endsection
