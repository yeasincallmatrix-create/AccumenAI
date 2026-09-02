@extends('layouts.standalone')

@php $backUrl = route('settings.index'); @endphp

@section('title', mawa_e('settings_page.account') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.account'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.account') }}</h4>
    <p>{{ mawa_e('settings_page.account_desc') }}</p>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-gear"></i> {{ mawa_e('settings_page.account') }}</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">{{ mawa_e('settings_page.account_name') }}</dt><dd class="col-sm-8">{{ $user->name ?? '' }}</dd>
        <dt class="col-sm-4">{{ mawa_e('settings_page.account_email') }}</dt><dd class="col-sm-8">{{ $user->email }}</dd>
        <dt class="col-sm-4">{{ mawa_e('settings_page.account_role') }}</dt><dd class="col-sm-8">{{ $roleLabel }}</dd>
    </dl>
</div>

@endsection