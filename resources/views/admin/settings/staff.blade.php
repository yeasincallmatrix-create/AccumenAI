@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', mawa_e('settings_page.staff_requests') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.staff_requests'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.staff_requests') }}</h4>
    <p>{{ mawa_e('settings_page.staff_requests_desc') }}</p>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-plus-fill"></i> Staff Registration Requests ({{ $pendingStaff->count() }})</div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Institute</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendingStaff as $staff)
                    <tr>
                        <td class="fw-semibold">{{ $staff->name }}</td>
                        <td>{{ $staff->institute->name ?? '—' }}</td>
                        <td>{{ $staff->email }}</td>
                        <td>{{ $staff->phone }}</td>
                        <td>{{ $staff->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end">
                            <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check-lg"></i> Approve</button>
                            </form>
                            <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}"
                                  onsubmit="return confirm('Reject registration for {{ $staff->name }}?');">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i> Reject</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No pending staff registrations.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection