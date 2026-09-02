@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', mawa_e('settings_page.change_password') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.change_password'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.change_password') }}</h4>
    <p>{{ mawa_e('settings_page.change_password_desc') }}</p>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-key"></i> Change Password</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.password') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
                @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Update Password</button>
    </form>
</div>

@endsection