@extends('layouts.institute')

@section('title', mawa_lang('preferences.title') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_lang('preferences.title') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('preferences.subtitle') }}</p>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-gear"></i> {{ mawa_lang('preferences.self_only') }}</div>
    </div>

    <form method="POST" action="{{ route('account.preferences.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="theme">{{ mawa_lang('preferences.theme') }}</label>
                <select id="theme" name="theme" class="form-select">
                    <option value="default" @selected($preferences['theme'] === 'default')>{{ mawa_lang('preferences.theme_default') }}</option>
                    <option value="light" @selected($preferences['theme'] === 'light')>{{ mawa_lang('preferences.theme_light') }}</option>
                    <option value="dark" @selected($preferences['theme'] === 'dark')>{{ mawa_lang('preferences.theme_dark') }}</option>
                </select>
                @error('theme')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="language">{{ mawa_lang('preferences.language') }}</label>
                <select id="language" name="language" class="form-select">
                    <option value="en" @selected($preferredLanguage === 'en')>English</option>
                    <option value="bn" @selected($preferredLanguage === 'bn')>বাংলা</option>
                </select>
                @error('language')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_lang('preferences.save') }}</button>
    </form>
</div>
@endsection
