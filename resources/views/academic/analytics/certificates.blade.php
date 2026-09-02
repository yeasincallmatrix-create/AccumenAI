@extends('layouts.institute')

@section('title', 'Certificate Analytics')

@section('content')

<x-academic.analytics.header
    title="Certificate Analytics"
    subtitle="Issued, revoked, pending and rejected certificates, with a per-course breakdown."
    export="{{ route('academic.analytics.certificates.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" />

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Issued', 'value' => $report['totals']['issued'], 'class' => 'text-success'],
        ['label' => 'Pending', 'value' => $report['totals']['pending'], 'class' => 'text-warning'],
        ['label' => 'Revoked', 'value' => $report['totals']['revoked'], 'class' => 'text-danger'],
        ['label' => 'Rejected', 'value' => $report['totals']['rejected'], 'class' => ''],
        ['label' => 'Total', 'value' => $report['totals']['total'], 'class' => ''],
        ['label' => 'Issued Rate', 'value' => $report['totals']['issued_rate'] !== null ? number_format($report['totals']['issued_rate'], 1).'%' : '—', 'class' => 'text-success'],
    ] as $stat)
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-patch-check-fill"></i></div>
                <div class="num {{ $stat['class'] }}">{{ $stat['value'] }}</div>
                <div class="label">{{ $stat['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-patch-check-fill"></i> By Course</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Course</th>
                    <th class="text-end">Issued</th>
                    <th class="text-end">Revoked</th>
                    <th class="text-end">Pending</th>
                    <th class="text-end">Rejected</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['byCourse'] as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['course']?->name ?? '—' }}</td>
                        <td class="text-end text-success">{{ $row['issued'] }}</td>
                        <td class="text-end text-danger">{{ $row['revoked'] }}</td>
                        <td class="text-end">{{ $row['pending'] }}</td>
                        <td class="text-end">{{ $row['rejected'] }}</td>
                        <td class="text-end fw-semibold">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No certificates found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection