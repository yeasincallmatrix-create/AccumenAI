@extends('layouts.institute')

@section('title', 'Vaccination Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-shield-plus"></i> Vaccination Dashboard</h4>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Due Today</h6>
                            <h2 class="mb-0 mt-1">{{ $dueToday->count() }}</h2>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Overdue</h6>
                            <h2 class="mb-0 mt-1">{{ $overdue->count() }}</h2>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Low Stock Items</h6>
                            <h2 class="mb-0 mt-1">{{ $lowStockCount }}</h2>
                        </div>
                        <i class="bi bi-box-seam fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Expiring (30 days)</h6>
                            <h2 class="mb-0 mt-1">{{ $expiringStocks->count() }}</h2>
                        </div>
                        <i class="bi bi-clock-history fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Due Today</h6></div>
                <div class="card-body">
                    @forelse($dueToday as $schedule)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $schedule->vaccineMaster->name ?? 'N/A' }}</strong> — Dose {{ $schedule->dose_number }}
                                <br><small class="text-muted">{{ $schedule->patient->full_name ?? 'N/A' }} | Due: {{ $schedule->due_date->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('medical.vaccination.schedules.administer', $schedule) }}" class="btn btn-primary btn-sm">Administer</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No vaccinations due today.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Overdue Schedules</h6></div>
                <div class="card-body">
                    @forelse($overdue->take(10) as $schedule)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $schedule->vaccineMaster->name ?? 'N/A' }}</strong> — Dose {{ $schedule->dose_number }}
                                <br><small class="text-muted">{{ $schedule->patient->full_name ?? 'N/A' }} | Due: {{ $schedule->due_date->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('medical.vaccination.schedules.administer', $schedule) }}" class="btn btn-outline-primary btn-sm">Administer</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No overdue schedules.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
