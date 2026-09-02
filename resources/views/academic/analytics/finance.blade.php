@extends('layouts.institute')

@section('title', 'Finance & Education Analytics')

@section('content')

@php
    $batchCourses = \App\Models\Course::whereIn('id', $report['batches']->pluck('course_id')->filter())->pluck('name', 'id');
@endphp

<x-academic.analytics.header
    title="Finance & Education Analytics"
    subtitle="Receivables, payables, net income and billed / outstanding / overdue amounts by course and batch."
    export="{{ route('academic.analytics.finance.export') }}"
/>

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Receivable', 'value' => $report['receivable'], 'class' => ''],
        ['label' => 'Payable', 'value' => $report['payable'], 'class' => ''],
        ['label' => 'Net Receivable', 'value' => $report['net'], 'class' => ''],
        ['label' => 'Net Income', 'value' => $report['net_income'], 'class' => $report['net_income'] >= 0 ? 'text-success' : 'text-danger'],
    ] as $stat)
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;"><i class="bi bi-cash-coin"></i></div>
                <div class="num {{ $stat['class'] }}">{{ number_format($stat['value'], 2) }}</div>
                <div class="label">{{ $stat['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-bar-chart-fill"></i> By Course</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Course</th>
                    <th class="text-end">Students</th>
                    <th class="text-end">Invoices</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Overdue</th>
                    <th class="text-end">Discounts</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['courses'] as $row)
                    <tr>
                        <td class="fw-semibold">
                            {{ $row->name }}
                            <div class="text-muted small">{{ $row->course_code }}</div>
                        </td>
                        <td class="text-end">{{ $row->student_count }}</td>
                        <td class="text-end">{{ $row->invoice_count }}</td>
                        <td class="text-end">{{ number_format($row->billed, 2) }}</td>
                        <td class="text-end fw-semibold {{ $row->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($row->outstanding, 2) }}</td>
                        <td class="text-end {{ $row->overdue > 0 ? 'text-danger' : '' }}">{{ number_format($row->overdue, 2) }}</td>
                        <td class="text-end">{{ number_format($row->discounts, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No billing activity found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-collection-fill"></i> By Batch</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Course</th>
                    <th class="text-end">Students</th>
                    <th class="text-end">Invoices</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Overdue</th>
                    <th class="text-end">Discounts</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['batches'] as $row)
                    <tr>
                        <td class="fw-semibold">
                            {{ $row->name }}
                            <div class="text-muted small">{{ $row->batch_code }}</div>
                        </td>
                        <td>{{ $batchCourses[$row->course_id] ?? '—' }}</td>
                        <td class="text-end">{{ $row->student_count }}</td>
                        <td class="text-end">{{ $row->invoice_count }}</td>
                        <td class="text-end">{{ number_format($row->billed, 2) }}</td>
                        <td class="text-end fw-semibold {{ $row->outstanding > 0 ? 'text-danger' : '' }}">{{ number_format($row->outstanding, 2) }}</td>
                        <td class="text-end {{ $row->overdue > 0 ? 'text-danger' : '' }}">{{ number_format($row->overdue, 2) }}</td>
                        <td class="text-end">{{ number_format($row->discounts, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No batch billing activity found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection