@extends('layouts.standalone')
@section('title', 'Performance Metrics — AccumenAI')
@section('page_title', 'Performance')

@section('content')
<div class="standalone-heading">
    <h4>Performance Metrics</h4>
    <p>Application performance monitoring for {{ $institute->name }}.</p>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Application Info</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>PHP Version</td><td class="text-end">{{ $appMetrics['php_version'] }}</td></tr>
                    <tr><td>Laravel Version</td><td class="text-end">{{ $appMetrics['laravel_version'] }}</td></tr>
                    <tr><td>Environment</td><td class="text-end"><span class="badge text-bg-{{ $appMetrics['environment'] === 'production' ? 'danger' : 'warning' }}">{{ $appMetrics['environment'] }}</span></td></tr>
                    <tr><td>Debug Mode</td><td class="text-end">{{ $appMetrics['debug'] ? 'ON' : 'OFF' }}</td></tr>
                    <tr><td>Timezone</td><td class="text-end">{{ $appMetrics['timezone'] }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Cache Metrics</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>Driver</td><td class="text-end">{{ $cacheMetrics['driver'] }}</td></tr>
                    <tr><td>Prefix</td><td class="text-end">{{ $cacheMetrics['prefix'] ?: '(none)' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="admin-card">
            <h6>Slow Query Indicators</h6>
            @if($slowQueries['available'] ?? false)
                @if(isset($slowQueries['queries']) && $slowQueries['queries']->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Query</th><th>Duration</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                            @foreach($slowQueries['queries'] as $query)
                            <tr>
                                <td class="small">{{ Str::limit($query->query ?? '', 100) }}</td>
                                <td>{{ $query->duration ?? 'N/A' }}ms</td>
                                <td class="text-nowrap">{{ $query->created_at ?? '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No slow queries recorded.</p>
                @endif
            @else
            <p class="text-muted mb-0">{{ $slowQueries['message'] ?? 'Slow query logging not available.' }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
