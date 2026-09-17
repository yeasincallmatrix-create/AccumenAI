@extends('layouts.institute')

@section('title', 'Diet Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $plan->plan_number }} — {{ $plan->plan_name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.diet.plans.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.diet.plans.edit', $plan) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Plan Details</h6>
                    <span class="badge bg-{{ $plan->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $plan->status)) }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Patient</dt><dd class="col-sm-8">{{ $plan->patient->full_name ?? 'N/A' }}</dd>
                        <dt class="col-sm-4">Diet Type</dt><dd class="col-sm-8"><span class="badge bg-{{ $plan->dietTypeColor() }}">{{ $plan->dietTypeLabel() }}</span></dd>
                        <dt class="col-sm-4">Period</dt><dd class="col-sm-8">{{ $plan->start_date->format('d M Y') }} → {{ $plan->end_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Calories</dt><dd class="col-sm-8">{{ $plan->daily_calories ?? '—' }} kcal/day</dd>
                        <dt class="col-sm-4">Protein/Carbs/Fat</dt><dd class="col-sm-8">{{ $plan->protein_grams ?? '—' }} / {{ $plan->carbs_grams ?? '—' }} / {{ $plan->fat_grams ?? '—' }} g</dd>
                        <dt class="col-sm-4">Sodium/Potassium</dt><dd class="col-sm-8">{{ $plan->sodium_mg ?? '—' }} / {{ $plan->potassium_mg ?? '—' }} mg</dd>
                        <dt class="col-sm-4">Fluid</dt><dd class="col-sm-8">{{ $plan->fluid_ml ?? '—' }} ml</dd>
                        @if($plan->restrictions)
                            <dt class="col-sm-4">Restrictions</dt><dd class="col-sm-8 text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $plan->restrictions }}</dd>
                        @endif
                        @if($plan->medical_notes)
                            <dt class="col-sm-4">Medical Notes</dt><dd class="col-sm-8">{{ $plan->medical_notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Adherence ({{ $adherence }}%)</h6></div>
                <div class="card-body">
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: {{ $adherence }}%">{{ $adherence }}%</div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Today: {{ $calories['actual'] }} / {{ $calories['target'] ?? '—' }} kcal ({{ $calories['percent'] }}%)
                    </p>
                    <div class="progress mt-1" style="height: 12px;">
                        <div class="progress-bar bg-info" style="width: {{ min(100, $calories['percent']) }}%"></div>
                    </div>
                </div>
            </div>

            @if($plan->isActive() && $user && $user->hasPermission('medical.diet.plan.edit'))
                <div class="card shadow-sm">
                    <div class="card-body d-flex gap-2 flex-wrap">
                        <form method="POST" action="{{ route('medical.diet.plans.generate-meals', $plan) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-info btn-sm">Regenerate Meals</button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#discontinueModal">Discontinue</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Meal Schedule ({{ $meals->count() }})</h6>
                    <a href="{{ route('medical.diet.plans.meals.create', $plan) }}" class="btn btn-outline-primary btn-sm">Add Meal</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle">
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
                                        <td>{{ $meal->meal_date->format('d M') }}</td>
                                        <td>{{ $meal->mealTypeLabel() }}</td>
                                        <td>{{ $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '—' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($meal->menu_items, 40) }}</td>
                                        <td>{{ $meal->calories ?? '—' }}</td>
                                        <td><span class="badge bg-{{ $meal->statusColor() }}">{{ ucfirst($meal->status) }}</span></td>
                                        <td>
                                            <a href="{{ route('medical.diet.plans.meals.show', [$plan, $meal]) }}" class="btn btn-outline-primary btn-sm">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No meals yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="discontinueModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('medical.diet.plans.discontinue', $plan) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Discontinue Plan</h6></div>
                <div class="modal-body">
                    <label class="form-label">Reason *</label>
                    <textarea name="discontinue_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Discontinue</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
