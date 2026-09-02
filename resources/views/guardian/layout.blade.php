<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', mawa_e('guardian.portal_name')) — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/layout.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="guardian-portal">

@php
    $activeStudent = $student ?? null;
    $studentList = $students ?? $guardian?->linkedStudents() ?? collect();
    $navLinks = [
        ['route' => 'guardian.dashboard', 'icon' => 'bi-house-door', 'label' => 'guardian.nav_dashboard'],
        ['route' => 'guardian.students', 'icon' => 'bi-people', 'label' => 'guardian.nav_students'],
        ['route' => 'guardian.notifications', 'icon' => 'bi-bell', 'label' => 'guardian.nav_notifications'],
    ];
@endphp

<header class="topbar guardian-topbar">
    <div class="container-fluid px-3 px-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="icon-btn d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#guardianNav" aria-controls="guardianNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <a class="d-flex align-items-center gap-2 fw-bold text-primary text-decoration-none" href="{{ route('guardian.dashboard') }}" style="font-size:18px">
                <i class="bi bi-person-heart"></i> {{ mawa_e('guardian.portal_name') }}
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if ($studentList->count() > 1 && $activeStudent !== null)
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-bounding-box me-1"></i>{{ $activeStudent->full_name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ mawa_e('guardian.switch_student') }}</h6></li>
                        @foreach ($studentList as $sw)
                            <li>
                                <form method="POST" action="{{ route('guardian.student.switch') }}">
                                    @csrf
                                    <input type="hidden" name="student_id" value="{{ $sw->id }}">
                                    <button class="dropdown-item {{ $activeStudent->id === $sw->id ? 'active' : '' }}" type="submit">
                                        <i class="bi bi-person me-1"></i>{{ $sw->full_name }}
                                        @if ($sw->id === $activeStudent->id)<i class="bi bi-check ms-1"></i>@endif
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="dropdown">
                <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('lang.label') }}">
                    <i class="bi bi-translate"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'en' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">English</a></li>
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'bn' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'bn']) }}">বাংলা</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('guardian.account') }}">
                    <i class="bi bi-person-circle"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">{{ $guardian->name }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('guardian.profile') }}"><i class="bi bi-person-gear me-1"></i>{{ mawa_e('guardian.nav_profile') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('guardian.logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-1"></i>{{ mawa_e('guardian.logout') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="collapse d-lg-block" id="guardianNav">
        <nav class="guardian-nav container-fluid px-3 px-md-4">
            <ul class="nav nav-pills flex-column flex-lg-row gap-1 gap-lg-2 py-2">
                @foreach ($navLinks as $link)
                    <li class="nav-item">
                        <a class="nav-link rounded-pill {{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}">
                            <i class="bi {{ $link['icon'] }} me-1"></i>{{ mawa_e($link['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>

<main class="guardian-main">
    <div class="container-fluid px-3 px-md-4 py-4">

        @if (session('status'))
            <div class="alert alert-success" data-auto-dismiss>
                <i class="bi bi-check-circle-fill me-1"></i> {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" data-auto-dismiss>
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
            </div>
        @endif

        @yield('content')

    </div>
</main>

<footer class="guardian-footer py-3 text-center small text-body-secondary">
    AccumenAI — {{ mawa_e('guardian.portal_name') }}
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
@stack('scripts')
</body>
</html>