@extends('layouts.institute')

@section('title', 'CRM → Admission Analytics')

@section('content')

<x-academic.analytics.header
    title="CRM → Admission Analytics"
    subtitle="Lead pipeline statuses, won / lost / open counts and conversion of leads into admitted students."
    export="{{ route('academic.analytics.crm.export') }}"
/>

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Contacts', 'value' => $report['contacts'], 'class' => ''],
        ['label' => 'Organizations', 'value' => $report['organizations'], 'class' => ''],
        ['label' => 'Leads', 'value' => $report['leads'], 'class' => ''],
        ['label' => 'Open', 'value' => $report['open'], 'class' => ''],
        ['label' => 'Won', 'value' => $report['won'], 'class' => 'text-success'],
        ['label' => 'Lost', 'value' => $report['lost'], 'class' => 'text-danger'],
        ['label' => 'Converted', 'value' => $report['converted'], 'class' => 'text-success'],
        ['label' => 'Conversion Rate', 'value' => $report['conversion_rate'] !== null ? number_format($report['conversion_rate'], 1).'%' : '—', 'class' => 'text-success'],
    ] as $stat)
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-diagram-2-fill"></i></div>
                <div class="num {{ $stat['class'] }}">{{ $stat['value'] }}</div>
                <div class="label">{{ $stat['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> Leads by Status</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-end">Leads</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['statuses'] as $status)
                    <tr>
                        <td>
                            <span class="badge" style="background: {{ $status->color ?? '#6c757d' }}">{{ $status->name }}</span>
                        </td>
                        <td class="text-end fw-semibold">{{ $report['byStatus']->get((int) $status->id) ?? 0 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center text-muted py-4">No lead statuses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection