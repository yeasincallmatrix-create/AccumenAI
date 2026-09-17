@extends('layouts.institute')

@section('title', 'Meals — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Meals — {{ $plan->plan_number }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm">Back to Plan</a>
            <a href="{{ route('medical.diet.plans.meals.create', $plan) }}" class="btn btn-primary btn-sm">Add Meal</a>
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="date" name="meal_date" class="form-control form-control-sm" value="{{ request('meal_date') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Medical\MealSchedule::STATUSES as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
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
                            <th>Date</th>
                            <th>Meal</th>
                            <th>Time</th>
                            <th>Menu</th>
                            <th>Kcal</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($meals as $meal)
                            <tr>
                                <td>{{ $meal->meal_date->format('d M Y') }}</td>
                                <td>{{ $meal->mealTypeLabel() }}</td>
                                <td>{{ $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($meal->menu_items, 60) }}</td>
                                <td>{{ $meal->calories ?? '—' }}</td>
                                <td><span class="badge bg-{{ $meal->statusColor() }}">{{ ucfirst($meal->status) }}</span></td>
                                <td>
                                    <a href="{{ route('medical.diet.plans.meals.show', [$plan, $meal]) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No meals found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $meals->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
