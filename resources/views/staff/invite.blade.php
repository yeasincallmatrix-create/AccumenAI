@extends('layouts.institute')

@section('title', mawa_lang('staff.title') . ' — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('staff.title') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('staff.subtitle', ['institute' => $institute->name ?? '']) }}</p>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" data-auto-dismiss>
        <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" data-auto-dismiss>
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-plus-fill"></i> {{ mawa_e('staff.invite_heading') }}</div>
    </div>

    <form method="POST" action="{{ route('staff.invite.store') }}">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="inv_first_name">{{ mawa_e('students.first_name') }}</label>
                <input id="inv_first_name" type="text" class="form-control" name="first_name" value="{{ old('first_name') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="inv_last_name">{{ mawa_e('students.last_name') }}</label>
                <input id="inv_last_name" type="text" class="form-control" name="last_name" value="{{ old('last_name') }}" required>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="inv_email">{{ mawa_e('auth.email') }}</label>
                <input id="inv_email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="inv_phone">{{ mawa_e('auth.phone') }}</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'inv_phone', 'value' => old('phone'), 'required' => true])
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="inv_role">{{ mawa_e('staff.role') }}</label>
            <select id="inv_role" name="role_id" class="form-select" required>
                <option value="">{{ mawa_e('staff.select_role') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="inv_password">{{ mawa_e('staff.temp_password') }}</label>
                <input id="inv_password" type="password" class="form-control" name="password" required autocomplete="new-password">
                <div class="form-text">{{ mawa_e('staff.temp_password_hint') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="inv_password_confirmation">{{ mawa_e('auth.confirm_password') }}</label>
                <input id="inv_password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>

        <button class="btn btn-primary" type="submit">
            <i class="bi bi-envelope-plus me-1"></i>{{ mawa_e('staff.invite_btn') }}
        </button>
    </form>
</div>

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-people-fill"></i> {{ mawa_e('staff.members_heading') }}</div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ mawa_e('staff.col_name') }}</th>
                    <th>Staff UID</th>
                    <th>{{ mawa_e('auth.email') }}</th>
                    <th>{{ mawa_e('staff.col_role') }}</th>
                    <th>{{ mawa_e('staff.col_type') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $index => $member)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $member->user?->name ?? '-' }}</td>
                        <td>@if($member->user?->uid)<x-uid-with-copy :uid="$member->user->uid" />@else<span class="text-muted">—</span>@endif</td>
                        <td>{{ $member->user?->email ?? '-' }}</td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border">{{ $member->role?->name ?? $member->role_id }}</span>
                        </td>
                        <td>
                            @if (($member->user?->isOwnerAccount() ?? false))
                                <span class="badge bg-success">{{ mawa_e('account_type.owner') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ mawa_e('account_type.staff') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ mawa_e('staff.members_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection