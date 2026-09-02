@extends('layouts.standalone')
@section('title','Learning Structure Settings')
@section('page_title','Learning Structure')

@section('content')
@php
    $labelLevels = collect($structure['levels'] ?? []);
@endphp

<div class="standalone-heading">
    <h4>Learning Structure</h4>
    <p>Generic N-level structure — template and levels are resolved from <code>LearningStructureResolver</code>. Works for School, University, Training Institute, Martial Arts, Dance, Music, Sports, Language, Coaching etc.</p>
</div>

@if (session('status'))
    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
@endif

{{-- Branch selector --}}
@if($branches->count())
<div class="admin-card mb-3">
    <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-diagram-3"></i> Branch</div></div>
    <form method="GET" action="{{ route('academic.structure.settings') }}" class="p-3 row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Branch</label>
            <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Shared (All branches)</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((int)$branchId === (int)$b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary" type="submit">Switch</button></div>
        <div class="col-12 small text-muted">Shared nodes (<code>branch_id = NULL</code>) visible to all. Branch-specific nodes only to that branch.</div>
    </form>
</div>
@endif

{{-- A. Current Template --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-layers"></i> Current Structure</div>
        @if($isCustomized)
            <span class="badge text-bg-warning">Customized (explicit)</span>
        @else
            <span class="badge text-bg-light border">Using Default</span>
        @endif
    </div>
    <div class="p-3">
        @if($template)
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <h5 class="mb-0">{{ $template->name }} <small class="text-muted">({{ $template->code }})</small></h5>
                <span class="badge text-bg-info">{{ $source }}</span>
                <span class="text-muted small">{{ $labelLevels->count() }} levels</span>
            </div>
            <div class="mb-2">
                <span class="fw-semibold small">Structure: </span>
                <span class="small">{{ $labelLevels->pluck('label')->implode(' → ') }}</span>
            </div>
            <p class="small text-muted mb-0">Global template reference only — no copy. Institute can customize via nodes below.</p>
        @else
            <p class="text-muted small mb-0">No template resolved.</p>
        @endif
    </div>
    <div class="border-top p-3">
        <form method="POST" action="{{ route('academic.structure.settings.assign') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label small">Switch Template</label>
                <select name="template_id" class="form-select form-select-sm">
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}" @selected($template && $t->id === $template->id)>{{ $t->name }} ({{ $t->code }}) — {{ $t->levels_count ?? $t->levels->count() }} levels</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit">Assign</button></div>
        </form>
        <div class="small text-muted mt-1">Only global templates can be assigned. Global template is never edited from here.</div>
    </div>
</div>

{{-- B. Structure Levels --}}
<div class="admin-card mb-3">
    <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-list-ol"></i> Structure Levels (N-level)</div></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Order</th><th>Level</th><th>Label</th><th>Value Source</th></tr></thead>
            <tbody>
            @forelse($labelLevels as $lvl)
                <tr>
                    <td>{{ $lvl['level_order'] }}</td>
                    <td>{{ $lvl['label'] }}</td>
                    <td><code>{{ $lvl['label_key'] }}</code></td>
                    <td>{{ $lvl['value_source'] ?? 'Custom' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted small">No levels.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- C. Structure Nodes (per level) + CRUD --}}
@foreach($labelLevels as $lvl)
@php
    $nodes = $lvl['nodes'] ?? [];
    // For level 1, nodes is tree with children; for deeper, flat. To show tree we use structure levels flat + parent.
    // We'll fetch flat nodes for display: structure has tree, but we can flatten via service for display simplicity.
    // For this view we show what resolver returned (level 1 tree, deeper flat).
@endphp
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-2"></i> Level {{ $lvl['level_order'] }}: {{ $lvl['label'] }}</div>
        <span class="badge text-bg-light border">Order {{ $lvl['level_order'] }}</span>
    </div>
    <div class="p-3">
        @if(empty($nodes))
            <p class="text-muted small mb-2">No nodes at this level.</p>
        @else
            @if($lvl['level_order'] === 1)
                <ul class="list-group list-group-flush mb-3">
                @foreach($nodes as $n)
                    @include('institute.partials.learning-node', ['node' => $n, 'level' => $lvl])
                @endforeach
                </ul>
            @else
                <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($nodes as $n)
                    <span class="badge text-bg-light border d-flex align-items-center gap-1">
                        {{ $n['name'] }}
                        @if(!empty($n['branch_id'])) <small class="text-muted">[branch {{ $n['branch_id'] }}]</small> @endif
                        <form method="POST" action="{{ route('academic.structure.settings.nodes.destroy', $n['id']) }}" class="d-inline ms-1">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:10px">x</button>
                        </form>
                    </span>
                @endforeach
                </div>
                {{-- Also show tree children under level 1 for context --}}
            @endif
        @endif

        {{-- Add node form --}}
        <form method="POST" action="{{ route('academic.structure.settings.nodes.store') }}" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="level_order" value="{{ $lvl['level_order'] }}">
            @if($lvl['level_order'] > 1)
                @php
                    $prevOrder = $lvl['level_order'] - 1;
                    $prevNodes = collect($structure['levels'])->firstWhere('level_order', $prevOrder)['nodes'] ?? [];
                    // Flatten tree for prev level if needed
                    $flatPrev = [];
                    $flatten = function($tree) use (&$flatten, &$flatPrev) {
                        foreach($tree as $n){ $flatPrev[] = $n; if(!empty($n['children'])) $flatten($n['children']); }
                    };
                    if($prevOrder === 1){ $flatten($prevNodes); } else { $flatPrev = $prevNodes; }
                @endphp
                <div class="col-md-4">
                    <label class="form-label small">Parent (Level {{ $prevOrder }})</label>
                    <select name="parent_node_id" class="form-select form-select-sm" required>
                        <option value="">-- Select parent --</option>
                        @foreach($flatPrev as $pn)
                            <option value="{{ $pn['id'] }}">{{ $pn['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-4">
                <label class="form-label small">Name</label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. {{ $lvl['label'] }} name" required maxlength="120">
            </div>
            @if($branches->count())
            <div class="col-md-2">
                <label class="form-label small">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">Shared</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected((int)$branchId === (int)$b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit">Add</button></div>
        </form>
    </div>
</div>
@endforeach

{{-- Dynamic cascading preview (reuse learning-select.js) --}}
<div class="admin-card mb-3">
    <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-ui-checks"></i> Dynamic Cascading Preview</div></div>
    <div class="p-3">
        <p class="small text-muted">This preview uses <code>learning-select.js</code> — Level 1 loads, then Level 2 depends on Level 1, etc. No hard-coded levels.</p>
        <div data-learning-component
             data-options-endpoint="{{ route('academic.structure.options') }}"
             data-nodes-endpoint="{{ route('academic.structure.nodes') }}"
             data-branch-id="{{ $branchId ?? '' }}"></div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/learning-select.js') }}"></script>
@endpush
