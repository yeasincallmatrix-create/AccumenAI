@extends('layouts.institute')

@section('title', 'Diet Template — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $template->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.diet.templates.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.diet.templates.edit', $template) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Diet Type</dt><dd class="col-sm-9">{{ $template->dietTypeLabel() }}</dd>
                <dt class="col-sm-3">Calories</dt><dd class="col-sm-9">{{ $template->total_calories ?? '—' }}</dd>
                <dt class="col-sm-3">Scope</dt><dd class="col-sm-9">{{ $template->isGlobal() ? 'Global' : 'Institute' }}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $template->is_active ? 'Active' : 'Inactive' }}</dd>
                @if($template->description)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $template->description }}</dd>@endif
                @if($template->meal_items)
                    <dt class="col-sm-3">Meal Items</dt>
                    <dd class="col-sm-9">
                        <ul class="mb-0">
                            @foreach($template->meal_items as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </dd>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
