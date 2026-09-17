@extends('layouts.institute')

@section('title', 'Kitchen Queue — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-basket"></i> Kitchen Queue — Today ({{ today()->format('d M Y') }})</h4>
        <a href="{{ route('medical.diet.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Meal</th>
                            <th>Patient</th>
                            <th>Plan</th>
                            <th>Menu</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $meal)
                            <tr>
                                <td><strong>{{ $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '—' }}</strong></td>
                                <td>{{ $meal->mealTypeLabel() }}</td>
                                <td>
                                    {{ $meal->dietPlan->patient->full_name ?? 'N/A' }}
                                    @if($meal->dietPlan->restrictions)
                                        <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> {{ \Illuminate\Support\Str::limit($meal->dietPlan->restrictions, 60) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $meal->dietPlan->dietTypeColor() }}">{{ $meal->dietPlan->dietTypeLabel() }}</span>
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($meal->menu_items, 80) }}</td>
                                <td><span class="badge bg-{{ $meal->statusColor() }}">{{ ucfirst($meal->status) }}</span></td>
                                <td class="text-nowrap">
                                    @if($user && $user->hasPermission('medical.diet.meal.serve'))
                                        @if($meal->status === 'scheduled')
                                            <form method="POST" action="{{ route('medical.diet.meals.prepare', $meal) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-info btn-sm">Prepare</button>
                                            </form>
                                        @endif
                                        @if($meal->status === 'prepared')
                                            <form method="POST" action="{{ route('medical.diet.meals.serve', $meal) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm">Serve</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No pending kitchen orders today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
