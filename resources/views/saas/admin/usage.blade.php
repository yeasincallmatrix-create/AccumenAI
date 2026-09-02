@extends('layouts.standalone')

@section('title', 'SaaS Usage Report — AccumenAI')
@section('page_title', 'Module Usage Analytics')

@section('content')
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-bar-chart-line"></i> Module Usage per Institute
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Institute</th>
                    <th>Package</th>
                    <th>Subscriptions</th>
                    <th>Enabled Modules</th>
                    @foreach($modules as $mod)
                        <th class="text-center">{{ $mod->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($usageData as $instId => $data)
                    <tr>
                        <td>{{ $data['institute']->name }}</td>
                        <td><span class="badge bg-info">{{ $data['institute']->package->name ?? 'FREE' }}</span></td>
                        <td>{{ $data['institute']->institute_subscriptions_count }}</td>
                        <td><span class="badge bg-primary">{{ $data['module_count'] }}</span></td>
                        @foreach($modules as $mod)
                            <td class="text-center">
                                @if(in_array($mod->key, $data['enabled_modules']))
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                    <i class="bi bi-x-circle text-muted"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 4 + count($modules) }}" class="text-center text-muted">No institutes found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
