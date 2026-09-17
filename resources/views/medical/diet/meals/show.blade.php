@extends('layouts.institute')

@section('title', 'Meal Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $meal->mealTypeLabel() }} — {{ $meal->meal_date->format('d M Y') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.diet.plans.meals.edit', [$plan, $meal]) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Meal Details</h6>
                    <span class="badge bg-{{ $meal->statusColor() }}">{{ ucfirst($meal->status) }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Patient</dt><dd class="col-sm-9">{{ $meal->dietPlan->patient->full_name ?? 'N/A' }}</dd>
                        <dt class="col-sm-3">Plan</dt><dd class="col-sm-9">{{ $meal->dietPlan->plan_number }} ({{ $meal->dietPlan->dietTypeLabel() }})</dd>
                        <dt class="col-sm-3">Time</dt><dd class="col-sm-9">{{ $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '—' }}</dd>
                        <dt class="col-sm-3">Menu</dt><dd class="col-sm-9">{{ $meal->menu_items }}</dd>
                        <dt class="col-sm-3">Calories</dt><dd class="col-sm-9">{{ $meal->calories ?? '—' }}</dd>
                        <dt class="col-sm-3">Prepared</dt><dd class="col-sm-9">{{ $meal->prepared_at?->format('d M Y H:i') ?? '—' }} {{ $meal->preparedBy ? '(' . $meal->preparedBy->name . ')' : '' }}</dd>
                        <dt class="col-sm-3">Served</dt><dd class="col-sm-9">{{ $meal->served_at?->format('d M Y H:i') ?? '—' }} {{ $meal->servedBy ? '(' . $meal->servedBy->name . ')' : '' }}</dd>
                        @if($meal->notes)<dt class="col-sm-3">Notes</dt><dd class="col-sm-9">{{ $meal->notes }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @if($user && $user->hasPermission('medical.diet.meal.serve'))
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0">Workflow</h6></div>
                    <div class="card-body d-grid gap-2">
                        @if($meal->status === 'scheduled')
                            <form method="POST" action="{{ route('medical.diet.meals.prepare', $meal) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-info btn-sm w-100">Mark Prepared</button>
                            </form>
                        @endif
                        @if(in_array($meal->status, ['scheduled', 'prepared']))
                            <form method="POST" action="{{ route('medical.diet.meals.serve', $meal) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm w-100">Mark Served</button>
                            </form>
                            <form method="POST" action="{{ route('medical.diet.meals.refuse', $meal) }}">
                                @csrf
                                <input type="text" name="notes" class="form-control form-control-sm mb-2" placeholder="Refusal reason (optional)">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Mark Refused</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
