@extends('layouts.standalone')

@section('title', 'Fee Structures — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Fee Structures</h4>
    <p>Billable composition for a branch / course / batch / academic year target, plus the installment plan. The most specific active structure is used when an enrollment is billed.</p>
    <div class="d-flex gap-2 flex-wrap">
        @if ($user->hasPermission('accounts.manage'))
            <a href="{{ route('finance.education.fee-structures.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Fee Structure</a>
        @endif
        <a href="{{ route('finance.education.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Education Finance</a>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Target</th>
                    <th>Items</th>
                    <th class="text-end">Total</th>
                    <th>Installments</th>
                    <th>Status</th>
                    @if ($user->hasPermission('accounts.manage'))
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($structures as $structure)
                    <tr>
                        <td>{{ $structure->name }}</td>
                        <td>
                            @php
                                $target = collect();
                                if ($structure->batch) $target->push('Batch: '.$structure->batch->name);
                                if ($structure->course) $target->push('Course: '.$structure->course->name);
                                if ($structure->academicYear) $target->push('Year: '.$structure->academicYear->name);
                                if ($target->isEmpty()) $target->push('Institute-wide');
                            @endphp
                            <span class="text-muted small">{{ $target->implode(', ') }}</span>
                        </td>
                        <td>
                            @foreach ($structure->items as $item)
                                <span class="badge text-bg-light text-muted me-1">{{ $item->feeHead?->name }} ({{ number_format($item->amount, 2) }})</span>
                            @endforeach
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($structure->total(), 2) }}</td>
                        <td>{{ $structure->installments_count }} &times; {{ $structure->installments_interval_days }} days</td>
                        <td><span class="badge text-bg-{{ $structure->status === 'active' ? 'success' : ($structure->status === 'archived' ? 'secondary' : 'warning') }}">{{ $structure->status }}</span></td>
                        @if ($user->hasPermission('accounts.manage'))
                            <td class="text-end">
                                <a href="{{ route('finance.education.fee-structures.edit', $structure) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('finance.education.fee-structures.destroy', $structure) }}" class="d-inline" onsubmit="return confirm('Delete this fee structure? Past invoices are unaffected.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No fee structures yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection