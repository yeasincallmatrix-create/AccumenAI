@extends('layouts.institute')

@section('title', 'Diet & Nutrition Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-egg-fried"></i> Diet & Nutrition</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.diet.kitchen.today') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-basket"></i> Kitchen Queue
            </a>
            @if($user && $user->hasPermission('medical.diet.plan.create'))
                <a href="{{ route('medical.diet.plans.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Diet Plan
                </a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Active Plans</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['active_plans'] }}</h2>
                        </div>
                        <i class="bi bi-journal-medical fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">On Hold</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['on_hold'] }}</h2>
                        </div>
                        <i class="bi bi-pause-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Meals Today</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['meals_today'] }}</h2>
                        </div>
                        <i class="bi bi-basket fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Served Today</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['served_today'] }}</h2>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Today's Meals</h6>
                    <a href="{{ route('medical.diet.kitchen.today') }}" class="btn btn-link btn-sm">Kitchen Queue</a>
                </div>
                <div class="card-body">
                    @forelse($todayMeals as $meal)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '' }}</strong>
                                — {{ $meal->mealTypeLabel() }}
                                <span class="badge bg-{{ $meal->statusColor() }}">{{ ucfirst($meal->status) }}</span>
                                <br><small class="text-muted">{{ $meal->dietPlan->patient->full_name ?? 'N/A' }} | {{ \Illuminate\Support\Str::limit($meal->menu_items, 60) }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No meals scheduled today.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Active Diet Plans</h6>
                    <a href="{{ route('medical.diet.plans.index') }}" class="btn btn-link btn-sm">All</a>
                </div>
                <div class="card-body">
                    @forelse($activePlans as $plan)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $plan->plan_number }}</strong> — {{ $plan->plan_name }}
                                <span class="badge bg-{{ $plan->dietTypeColor() }}">{{ $plan->dietTypeLabel() }}</span>
                                <br><small class="text-muted">{{ $plan->patient->full_name ?? 'N/A' }} | {{ $plan->daily_calories ?? '—' }} kcal/day</small>
                            </div>
                            <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No active plans.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
