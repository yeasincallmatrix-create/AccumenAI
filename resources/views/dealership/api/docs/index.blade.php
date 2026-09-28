@extends('layouts.standalone')

@section('title', 'API Docs — AccumenAI')
@section('page_title', 'API Docs')

@section('content')
<div class="standalone-heading">
    <h4>API Docs (ডেভেলপার ডকুমেন্টেশন)</h4>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('dealership.api.docs.regenerate') }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Regenerate</button></form>
    </div>
</div>

@include('dealership.api._nav')

<p class="text-muted">Version {{ $docs['version'] }} · {{ $docs['endpoint_count'] }} endpoints · generated {{ $docs['generated_at'] }}</p>

<div class="accordion" id="apiDocsAccordion">
    @forelse($docs['versions'] as $version => $endpoints)
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ver-{{ $version }}">{{ $version }} ({{ count($endpoints) }})</button></h2>
            <div id="ver-{{ $version }}" class="accordion-collapse collapse show">
                <div class="accordion-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Key</th><th>Method</th><th>URI</th><th>Permission</th><th>Enabled</th></tr></thead>
                        <tbody>
                            @foreach($endpoints as $ep)
                                <tr><td><code>{{ $ep['key'] }}</code></td><td><span class="badge bg-secondary">{{ $ep['method'] }}</span></td><td><code>{{ $ep['uri'] }}</code></td><td>{{ $ep['required_permission'] ?? '—' }}</td><td>{{ $ep['is_enabled'] ? 'Yes' : 'No' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="admin-card card"><div class="card-body text-muted">কোনো ডকস নেই — Regenerate চাপুন (No docs yet — hit Regenerate)</div></div>
    @endforelse
</div>
@endsection
