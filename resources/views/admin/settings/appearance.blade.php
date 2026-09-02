@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', mawa_e('settings_page.appearance') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.appearance'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.appearance') }}</h4>
    <p>{{ mawa_e('settings_page.appearance_desc') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
    </div>
@endif

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-palette"></i> {{ mawa_e('settings_page.theme_heading') }}</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.appearance.update') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label class="form-label">Theme</label>
                <div class="row g-3">
                    @foreach ($themes as $item)
                        <div class="col-6 col-md-4 col-lg-3">
                            <label class="theme-option {{ $activeTheme?->id === $item->id ? 'selected' : '' }}" data-theme-option>
                                <input type="radio" name="theme_id" value="{{ $item->id }}" {{ $activeTheme?->id === $item->id ? 'checked' : '' }}>
                                <div class="theme-swatch">
                                    <div class="swatch-primary" style="background:{{ $item->primary_color }}"></div>
                                    <div class="swatch-secondary" style="background:{{ $item->secondary_color }}"></div>
                                </div>
                                <span class="theme-name">{{ $item->name }}</span>
                                @if ($activeTheme?->id === $item->id)
                                    <i class="bi bi-check-circle-fill theme-check"></i>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('theme_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sidebar_color">Navigation Drawer Color</label>
                <input type="color" id="sidebar_color" name="sidebar_color" class="form-control form-control-color"
                       value="{{ $sidebarColor ?? '#FFFFFF' }}">
                @error('sidebar_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Tall Navigation</label>
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" id="tall_navigation" name="tall_navigation" value="1" @checked($tallNavigation ?? false)>
                    <label class="form-check-label small text-muted" for="tall_navigation">
                        Topbar stretches full width, sidebar sits below it
                    </label>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
    </form>
</div>

@endsection

@section('scripts')
<script>
(function () {
    document.querySelectorAll('[data-theme-option]').forEach(function (label) {
        var input = label.querySelector('input');
        label.addEventListener('click', function () {
            document.querySelectorAll('[data-theme-option]').forEach(function (other) {
                other.classList.remove('selected');
                var check = other.querySelector('.theme-check');
                if (check) check.remove();
            });
            label.classList.add('selected');
            var check = label.querySelector('.theme-check');
            if (!check) {
                check = document.createElement('i');
                check.className = 'bi bi-check-circle-fill theme-check';
                label.appendChild(check);
            }
            input.checked = true;
        });
    });
})();
</script>
@endsection