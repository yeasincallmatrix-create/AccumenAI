@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', mawa_e('settings_page.account') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.account'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.account') }}</h4>
    <p>{{ mawa_e('settings_page.account_desc') }}</p>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-gear"></i> Account</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $admin->name ?? 'Yasin Sheikh' }}</dd>
        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $admin->email }}</dd>
        <dt class="col-sm-4">Role</dt><dd class="col-sm-8">{{ $roleLabel }}</dd>
    </dl>
</div>

<div class="admin-card mt-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-translate"></i> Language</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.language') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="language">Language</label>
                <select id="language" name="language" class="form-select">
                    <option value="en" {{ $preferredLanguage === 'en' ? 'selected' : '' }}>English</option>
                    <option value="bn" {{ $preferredLanguage === 'bn' ? 'selected' : '' }}>বাংলা</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-translate"></i> Save Language</button>
    </form>
</div>

@endsection