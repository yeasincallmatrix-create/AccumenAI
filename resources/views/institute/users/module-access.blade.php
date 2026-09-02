@extends('layouts.institute')

@section('title', 'User Access — ' . $institute->name)

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Module Access — {{ $user->name ?? $user->email }}</h4>
        <p class="page-header-desc">{{ $institute->name }} — Disable any module for this user. Default is package/industry; admin override wins.</p>
    </div>
    <a href="{{ route('staff.invite') }}" class="btn btn-outline-secondary btn-sm">Back to Team</a>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="admin-card">
    <form method="POST" action="{{ route('institute.users.modules.update', $user->getKey()) }}">
        @csrf
        @method('PUT')
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Module</th><th>Institute Has?</th><th>User Visible?</th><th>Enable</th></tr></thead>
                <tbody>
                    @foreach($allModules as $mod)
                        @php
                            $hasInstitute = in_array($mod->key, $instituteEnabled, true);
                            $isVisible = app(\App\Services\UserModuleAccessService::class)->isEnabledForUser($institute, $user, $mod->key);
                            $checked = $isVisible;
                        @endphp
                        <tr>
                            <td><strong>{{ $mod->name }}</strong> <code>{{ $mod->key }}</code></td>
                            <td>@if($hasInstitute)<span class="badge text-bg-success">Yes</span>@else<span class="badge text-bg-secondary">No</span>@endif</td>
                            <td>@if($isVisible)<span class="badge text-bg-primary">Visible</span>@else<span class="badge text-bg-warning">Hidden</span>@endif</td>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="modules[]" value="{{ $mod->key }}" id="mod_{{ $mod->key }}" {{ $checked ? 'checked' : '' }}><label class="form-check-label" for="mod_{{ $mod->key }}">{{ $checked ? 'Enabled' : 'Disabled' }}</label></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3"><label class="form-label">Reason (optional)</label><input type="text" name="reason" class="form-control" placeholder="e.g. Hide payroll for cashier"></div>
        <div class="mt-3"><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div>
@endsection
