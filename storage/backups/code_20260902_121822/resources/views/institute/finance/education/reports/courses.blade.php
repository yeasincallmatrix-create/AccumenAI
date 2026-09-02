@extends('layouts.standalone')

@section('title', 'Course Finance Report — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Course-wise Finance Report</h4>
    <p>Billed, collected-relevant, outstanding, overdue and discounted amounts grouped by course. Derived from the posted ledger via the finance core.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('finance.education.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Education Finance</a>
        <a href="{{ route('finance.education.reports.batches') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart-fill me-1"></i>Batch Report</a>
    </div>
</div>

<div class="admin-card">
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
                @forelse ($rows as $row)
                    <tr>
                        <td>
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

@endsection