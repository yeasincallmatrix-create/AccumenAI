@extends('layouts.standalone')

@php $backUrl = $securityGuard === 'platform_admin' ? route('admin.settings.index') : route('settings.index'); @endphp

@section('title', mawa_e('security.title') . ' — AccumenAI')
@section('page_title', mawa_e('security.title'))

@section('content')

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('security.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('security.subtitle') }}</p>
    </div>
</div>

@include('security._panel')

@endsection
