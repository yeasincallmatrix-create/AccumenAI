@extends('layouts.institute')

@section('title', 'Vaccine Stock — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-seam"></i> Vaccine Stock</h4>
        @if($user && $user->hasPermission('medical.vaccination.stock.manage'))
            <a href="{{ route('medical.vaccination.stocks.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add Stock
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="vaccine_master_id" class="form-select form-select-sm">
                        <option value="">All Vaccines</option>
                        @foreach($vaccines as $v)
                            <option value="{{ $v->id }}" {{ request('vaccine_master_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(\App\Models\Medical\VaccineStock::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Vaccine</th>
                            <th>Batch</th>
                            <th>Received</th>
                            <th>Used</th>
                            <th>Available</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks as $s)
                            <tr>
                                <td><strong>{{ $s->vaccineMaster->name ?? 'N/A' }}</strong></td>
                                <td>{{ $s->batch_number }}</td>
                                <td>{{ $s->quantity_received }}</td>
                                <td>{{ $s->quantity_used }}</td>
                                <td>{{ $s->quantity_available }}</td>
                                <td>{{ $s->expiry_date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ $s->statusColor() }}">{{ $s->statusLabel() }}</span></td>
                                <td>
                                    @if($user && $user->hasPermission('medical.vaccination.stock.manage'))
                                        <a href="{{ route('medical.vaccination.stocks.edit', $s) }}" class="btn btn-outline-warning btn-sm">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No stock records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $stocks->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
