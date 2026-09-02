<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}" data-user-theme="{{ $userTheme }}" @class([
    'monetix-dark' => $userTheme === 'dark',
    'monetix-tall-nav' => $tallNavigation ?? false,
]) data-bs-theme="{{ $userTheme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>@yield('title', 'AccumenAI')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/base.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/layout.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/layout.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/components.css')) }}" rel="stylesheet">
    @include('layouts.partials.theme_colors')
    @stack('styles')
</head>
<body>

@include('partials.page_marker')

<div class="topbar">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="icon-btn sidebar-toggle" type="button" id="monetixSidebarToggle" aria-label="{{ mawa_e('sidebar.toggle') }}" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            @if ($isInstituteStaff && !empty($institute->slug))
                <a class="brand" href="{{ route('business.show', $institute->slug) }}" title="View business page">
                    {{ $institute->name ?? 'AccumenAI' }}
                    @if ($institute)
                        <i class="bi bi-patch-check-fill verified-badge is-verified" title="Verified"></i>
                    @endif
                </a>
            @else
                <a class="brand" href="{{ route('dashboard') }}">
                    @if ($isInstituteStaff)
                        {{ $institute->name ?? 'AccumenAI' }}
                        @if ($institute)
                            <i class="bi bi-patch-check-fill verified-badge is-verified" title="Verified"></i>
                        @endif
                    @endif
                    @auth('platform_admin')
                        AccumenAI
                    @endauth
                </a>
            @endif
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="notification-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ mawa_e('notifications.title') }}">
                    <i class="bi bi-bell"></i>
                    @if ($user && $layoutUnreadCount > 0)
                        <span class="bell-dot show" id="monetixNotifDot">{{ $layoutUnreadCount > 99 ? '99+' : $layoutUnreadCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-menu">
                    <div class="notification-menu-header">
                        <span>{{ mawa_e('notifications.title') }}</span>
                        @if ($user)
                            <button type="button" class="notification-mark-all" id="monetixNotifReadAll" data-read-all-url="{{ $notificationReadAllUrl }}">{{ mawa_e('notifications.mark_all_read') }}</button>
                        @endif
                    </div>
                    <div class="notification-list" id="monetixNotifList">
                        @if ($user)
                            @forelse ($layoutNotifications as $notification)
                                <a class="notification-item {{ in_array($notification->id, $layoutReadIds, true) ? 'read' : 'unread' }}"
                                   href="{{ $notification->link_url ?: $notificationIndexUrl }}"
                                   data-id="{{ $notification->id }}"
                                   data-mark-read-url="{{ auth('platform_admin')->check() ? route('admin.notifications.read', $notification->id) : route('notifications.read', $notification->id) }}">
                                    <div class="notification-item-title">{{ $notification->title }}</div>
                                    <div class="notification-item-msg">{{ $notification->message }}</div>
                                    <div class="notification-item-time">{{ $notification->created_at?->diffForHumans() ?? '' }}</div>
                                </a>
                            @empty
                                <div class="notification-empty"><i class="bi bi-bell-slash"></i> {{ mawa_e('notifications.empty') }}</div>
                            @endforelse
                        @else
                            <div class="notification-empty"><i class="bi bi-bell-slash"></i> {{ mawa_e('notifications.empty') }}</div>
                        @endif
                    </div>
                    @if ($user)
                        <a class="notification-menu-footer" href="{{ $notificationIndexUrl }}">{{ mawa_e('notifications.view_all') }}</a>
                    @endif
                </div>
            </div>
            <div class="dropdown">
                <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('lang.label') }}">
                    <i class="bi bi-translate"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'en' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">English</a></li>
                    <li><a class="dropdown-item {{ mawa_current_lang() === 'bn' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'bn']) }}">বাংলা</a></li>
                </ul>
            </div>
            @auth('platform_admin')
                <form method="POST" action="{{ route('admin.dev.page-marker.toggle') }}" class="d-inline-block">
                    @csrf
                    <button class="icon-btn page-marker-toggle" type="submit" title="{{ (\App\Support\PageMarker::enabled() ? 'ON' : 'OFF') }} — Page Marker toggle" aria-label="Toggle Page Marker">
                        <i class="bi {{ \App\Support\PageMarker::enabled() ? 'bi-signpost-2-fill text-warning' : 'bi-signpost-2' }}"></i>
                    </button>
                </form>
            @endauth
            <button class="icon-btn" type="button" id="monetixDarkToggle" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" id="monetixDarkIcon"></i>
            </button>
        </div>
    </div>
</div>

<div class="sidebar-backdrop" id="monetixSidebarBackdrop"></div>

<div class="layout">

    <aside class="sidebar" @if ($sidebarColor) style="--sidebar-bg: {{ $sidebarColor }};" @endif>
        <nav class="nav flex-column">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <i class="bi bi-grid-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.dashboard') }}</span>
            </a>
            @if ($isInstituteStaff)
                @php $isEducation = ($institute->industry ?? '') === 'education'; @endphp
                @php $hasEducationModule = $workspaceAllowedEducation ?? false; @endphp
                @if ($isEducation)
                    @php
                        $academicHref = $usesClassTerm ? route('classes.index') : route('courses.manage.index');
                        $academicActive = $usesClassTerm
                            ? request()->routeIs('classes.*')
                            : request()->routeIs('courses.manage.*', 'courses.show', 'courses.archive', 'courses.subjects', 'courses.subjects.*', 'batches.*');
                        $academicLabel = $usesClassTerm ? mawa_e('sidebar.classes') : mawa_e('sidebar.courses');
                    @endphp
                @endif
                @if ($isEducation && $hasEducationModule)
                    <a class="nav-link {{ request()->routeIs('students.*') ? 'active' : '' }}" href="{{ route('students.index') }}">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.students') }}</span>
                    </a>
                @endif
                @if ($isEducation && ($workspaceAllowedTeachers ?? false))
                    <a class="nav-link {{ request()->routeIs('teachers.*') ? 'active' : '' }}" href="{{ route('teachers.index') }}>
                        <i class="bi bi-person-workspace"></i><span class="sidebar-label">Teachers</span>
                    </a>
                @endif
                @if ($workspaceAllowedHr ?? false)
                    <a class="nav-link {{ request()->routeIs('hr.payroll.*','hr.performance.*','hr.salary-structures.*','hr.training.*') ? 'active' : '' }}" href="{{ route('hr.payroll.periods.index') }}">
                        <i class="bi bi-cash-coin"></i><span class="sidebar-label">Payroll</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}" href="{{ route('hr.dashboard') }}">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">HR</span>
                    </a>
                @endif
                @if ($workspaceAllowedSales ?? false)
                    <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.settings.index') }}">
                        <i class="bi bi-cart-fill"></i><span class="sidebar-label">Sales</span>
                    </a>
                @endif
                @if ($workspaceAllowedPurchase ?? false)
                    <a class="nav-link {{ request()->routeIs('purchase.orders.*','purchase.quotations.*','purchase.returns.*') ? 'active' : '' }}" href="{{ route('purchase.orders.index') }}">
                        <i class="bi bi-bag-fill"></i><span class="sidebar-label">Purchase</span>
                    </a>
                @endif
                @if ($isEducation && ($workspaceAllowedEducation ?? false))
                    <a class="nav-link {{ $academicActive ? 'active' : '' }}" href="{{ $academicHref }}">
                        <i class="bi bi-journal-bookmark-fill"></i><span class="sidebar-label">{{ $academicLabel }}</span>
                    </a>
                    @php
                        $canViewAlumni = $user instanceof \App\Models\InstituteUser
                            ? $user->hasPermission('alumni.view')
                            : (\App\Support\Workspace::membership()?->hasPermission('alumni.view') ?? false);
                    @endphp
                    @if ($canViewAlumni)
                        <a class="nav-link {{ request()->routeIs('alumni.*') ? 'active' : '' }}" href="{{ route('alumni.index') }}">
                            <i class="bi bi-award-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.alumni') }}</span>
                        </a>
                    @endif
                @endif
                @if ($isEducation && ($workspaceAllowedEducation ?? false))
                    @php
                        $canViewWorkflows = $user instanceof \App\Models\InstituteUser
                            ? $user->hasPermission('workflows.view')
                            : (\App\Support\Workspace::membership()?->hasPermission('workflows.view') ?? false);
                    @endphp
                    @if ($canViewWorkflows)
                        <a class="nav-link {{ request()->routeIs('workflows.*') ? 'active' : '' }}" href="{{ route('workflows.index') }}">
                            <i class="bi bi-diagram-3-fill"></i><span class="sidebar-label">Workflows</span>
                        </a>
                    @endif
                @endif
                @if ($workspaceAllowedCrm ?? false)
                    <a class="nav-link {{ request()->routeIs('crm.*') ? 'active' : '' }}" href="{{ route('crm.dashboard') }}">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">CRM</span>
                    </a>
                @endif
                @if ($workspaceAllowedStaffManage ?? false)
                    <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.invite') }}">
                        <i class="bi bi-person-plus-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.team') }}</span>
                    </a>
                @endif
                @if ($workspaceAllowedFinance ?? false)
                    <a class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}" href="{{ route('finance.dashboard') }}">
                        <i class="bi bi-cash-coin"></i><span class="sidebar-label">Finance</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.chart-of-accounts.*') ? 'active' : '' }}" href="{{ route('finance.chart-of-accounts.index') }}">
                        <i class="bi bi-list-columns-reverse"></i><span class="sidebar-label">Chart of Accounts</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.journals.*') ? 'active' : '' }}" href="{{ route('finance.journals.index') }}">
                        <i class="bi bi-journal-text"></i><span class="sidebar-label">Journals</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.invoices.*') || request()->routeIs('finance.payments.*') ? 'active' : '' }}" href="{{ route('finance.invoices.index') }}">
                        <i class="bi bi-receipt-cutoff"></i><span class="sidebar-label">Invoices</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.payments.index') ? 'active' : '' }}" href="{{ route('finance.payments.index') }}">
                        <i class="bi bi-cash-stack"></i><span class="sidebar-label">Payments</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.parties.*') ? 'active' : '' }}" href="{{ route('finance.parties.index') }}">
                        <i class="bi bi-people"></i><span class="sidebar-label">Parties</span>
                    </a>
                    @if ($workspaceAllowedAccountingManage ?? false)
                        <a class="nav-link sub {{ request()->routeIs('finance.payment-methods.*') ? 'active' : '' }}" href="{{ route('finance.payment-methods.index') }}">
                            <i class="bi bi-wallet2"></i><span class="sidebar-label">Payment Methods</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.periods.*') ? 'active' : '' }}" href="{{ route('finance.periods.index') }}">
                            <i class="bi bi-calendar-range"></i><span class="sidebar-label">Fiscal Years &amp; Periods</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.opening-balances.*') ? 'active' : '' }}" href="{{ route('finance.opening-balances.create') }}">
                            <i class="bi bi-box-arrow-in-down"></i><span class="sidebar-label">Opening Balances</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.exchange-rates.*') ? 'active' : '' }}" href="{{ route('finance.exchange-rates.index') }}">
                            <i class="bi bi-currency-exchange"></i><span class="sidebar-label">Exchange Rates</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.fx-revaluations.*') ? 'active' : '' }}" href="{{ route('finance.fx-revaluations.index') }}">
                            <i class="bi bi-calculator"></i><span class="sidebar-label">FX Revaluation</span>
                        </a>
                    @endif
                    <a class="nav-link sub {{ request()->routeIs('finance.audit.*') ? 'active' : '' }}" href="{{ route('finance.audit.index') }}">
                        <i class="bi bi-shield-lock"></i><span class="sidebar-label">Audit Trail</span>
                    </a>
                    @if ($isEducation)
                        <a class="nav-link sub {{ request()->routeIs('finance.education.dashboard') || request()->routeIs('finance.education.students.*') || request()->routeIs('finance.education.reports.*') ? 'active' : '' }}" href="{{ route('finance.education.dashboard') }}">
                            <i class="bi bi-mortarboard-fill"></i><span class="sidebar-label">Education Fees</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.education.fee-collection*') ? 'active' : '' }}" href="{{ route('finance.education.fee-collection') }}">
                            <i class="bi bi-cash-stack"></i><span class="sidebar-label">Fee Collection</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.education.fee-heads.*') ? 'active' : '' }}" href="{{ route('finance.education.fee-heads.index') }}">
                            <i class="bi bi-tag"></i><span class="sidebar-label">Fee Heads</span>
                        </a>
                        <a class="nav-link sub {{ request()->routeIs('finance.education.fee-structures.*') ? 'active' : '' }}" href="{{ route('finance.education.fee-structures.index') }}">
                            <i class="bi bi-list-check"></i><span class="sidebar-label">Fee Structures</span>
                        </a>
                    @endif
                    @php
                        $canViewBudgets = $user instanceof \App\Models\InstituteUser
                            ? $user->hasPermission('budget.view')
                            : (\App\Support\Workspace::membership()?->hasPermission('budget.view') ?? false);
                    @endphp
                    @if ($canViewBudgets)
                        <a class="nav-link sub {{ request()->routeIs('finance.budgets.*') ? 'active' : '' }}" href="{{ route('finance.budgets.dashboard') }}">
                            <i class="bi bi-wallet2"></i><span class="sidebar-label">Budgets</span>
                        </a>
                    @endif
                    <a class="nav-link sub {{ request()->routeIs('finance.online-payments.*') || request()->routeIs('online-payments.*') ? 'active' : '' }}" href="{{ route('finance.online-payments.gateways') }}">
                        <i class="bi bi-credit-card"></i><span class="sidebar-label">Online Payments</span>
                    </a>
                    <a class="nav-link sub {{ request()->routeIs('finance.reports.*') ? 'active' : '' }}" href="{{ route('finance.reports.trial-balance') }}">
                        <i class="bi bi-bar-chart-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.reports') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('sync.*') ? 'active' : '' }}" href="{{ route('sync.index') }}">
                        <i class="bi bi-arrow-repeat"></i><span class="sidebar-label">{{ mawa_e('sidebar.offline_review') }}</span>
                        @if ($countsPendingSync ?? false)
                            <span class="badge bg-warning ms-auto">{{ $countsPendingSync }}</span>
                        @endif
                    </a>
                @endif
                @if ($aiEnabled ?? false)
                    <a class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}" href="{{ route('ai.assistant') }}">
                        <i class="bi bi-robot"></i><span class="sidebar-label">{{ mawa_e('sidebar.ai_assistant') }}</span>
                    </a>
                @endif
                <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
                    <i class="bi bi-gear-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.settings') }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('recycle.*') ? 'active' : '' }}" href="{{ route('recycle.index') }}">
                    <i class="bi bi-trash-fill"></i><span class="sidebar-label">{{ mawa_e('sidebar.recycle_bin') }}</span>
                    @if ($recycleCount > 0)
                        <span class="badge bg-danger ms-auto">{{ $recycleCount }}</span>
                    @endif
                </a>
            @else
                @auth('platform_admin')
                    @php
                        $selectedIndustryKey = request()->query('industry');
                        $selectedIndustryLabel = is_string($selectedIndustryKey) && $selectedIndustryKey !== ''
                            ? (\App\Support\IndustryRules::industries(null)[$selectedIndustryKey] ?? ucwords(str_replace('_', ' ', $selectedIndustryKey)))
                            : 'All Industries';
                        $isEducationIndustry = $selectedIndustryKey === 'education'
                            || request()->routeIs('admin.institutes.*')
                            || request()->routeIs('admin.courses.*')
                            || request()->routeIs('admin.classes.*')
                            || request()->routeIs('admin.students.*')
                            || request()->routeIs('admin.certificates.*');
                    @endphp

                    {{-- ═══ MAIN ═══ --}}
                    <div class="sidebar-section-label">MAIN</div>

                    <a class="nav-link {{ request()->routeIs('admin.tenants.index') ? 'active' : '' }}" href="{{ route('admin.tenants.index') }}">
                        <i class="bi bi-building"></i><span class="sidebar-label">Tenant</span>
                    </a>

                    @if ($isEducationIndustry)
                    {{-- ═══ EDUCATION ═══ --}}
                    <div class="sidebar-section-label">EDUCATION</div>

                    <a class="nav-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}" href="{{ route('admin.courses.index', ['industry' => 'education']) }}">
                        <i class="bi bi-journal-bookmark-fill"></i><span class="sidebar-label">{{ mawa_e('nav.courses') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.classes.*') ? 'active' : '' }}" href="{{ route('admin.classes.index', ['industry' => 'education']) }}">
                        <i class="bi bi-diagram-3-fill"></i><span class="sidebar-label">Classes</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index', ['industry' => 'education']) }}">
                        <i class="bi bi-person-badge"></i><span class="sidebar-label">{{ mawa_e('nav.student_registration') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.certificates.*') ? 'active' : '' }}" href="{{ route('admin.certificates.requests', ['industry' => 'education']) }}">
                        <i class="bi bi-patch-check-fill"></i><span class="sidebar-label">{{ mawa_e('nav.certificates') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.academic.*') ? 'active' : '' }}" href="{{ route('admin.academic.index', ['industry' => 'education']) }}">
                        <i class="bi bi-diagram-3-fill"></i><span class="sidebar-label">Academic Structure</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.academic.subjects.*') ? 'active' : '' }}" href="{{ route('admin.academic.subjects.index', ['industry' => 'education']) }}">
                        <i class="bi bi-collection-fill"></i><span class="sidebar-label">Academic Subjects</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.academic.grading.*') ? 'active' : '' }}" href="{{ route('admin.academic.grading.index', ['industry' => 'education']) }}">
                        <i class="bi bi-award-fill"></i><span class="sidebar-label">Grade Scales</span>
                    </a>
                    @endif

                    {{-- ═══ OPERATIONS ═══ --}}
                    <div class="sidebar-section-label">OPERATIONS</div>

                    <a class="nav-link {{ request()->routeIs('admin.modules.*', 'admin.packages.*') ? 'active' : '' }}" href="{{ route('admin.modules.index') }}">
                        <i class="bi bi-puzzle-fill"></i><span class="sidebar-label">Modules &amp; Packages</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.modules.access-logs') ? 'active' : '' }}" href="{{ route('admin.modules.access-logs') }}">
                        <i class="bi bi-clock-history"></i><span class="sidebar-label">Module Access Logs</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.industry-settings') ? 'active' : '' }}" href="{{ $selectedIndustryKey && $selectedIndustryKey !== '' ? route('admin.industry-settings', ['industry' => $selectedIndustryKey]) : route('admin.industry-settings') }}">
                        <i class="bi bi-gear-fill"></i><span class="sidebar-label">Industry Settings</span>
                    </a>

                    {{-- ═══ CONFIGURATION ═══ --}}
                    <div class="sidebar-section-label">CONFIGURATION</div>

                    <a class="nav-link {{ request()->routeIs('admin.platform-settings.*') ? 'active' : '' }}" href="{{ route('admin.platform-settings.index') }}">
                        <i class="bi bi-sliders"></i><span class="sidebar-label">Configuration Center</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.settings.*') && !request()->routeIs('admin.platform-settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                        <i class="bi bi-gear"></i><span class="sidebar-label">Legacy Settings</span>
                    </a>

                    {{-- ═══ SECURITY ═══ --}}
                    <div class="sidebar-section-label">SECURITY</div>

                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                        <i class="bi bi-people-fill"></i><span class="sidebar-label">All Accounts</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.platform-audit.*') ? 'active' : '' }}" href="{{ route('admin.platform-audit.index') }}">
                        <i class="bi bi-journal-text"></i><span class="sidebar-label">Platform Audit</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.institutes.bin') ? 'active' : '' }}" href="{{ route('admin.institutes.bin', ['industry' => $selectedIndustryKey ?? null]) }}">
                        <i class="bi bi-trash-fill"></i><span class="sidebar-label">Recycle Bin</span>
                        @if ($recycleCount > 0)
                            <span class="badge bg-danger ms-auto">{{ $recycleCount }}</span>
                        @endif
                    </a>

                    {{-- ═══ DATABASE ═══ --}}
                    <div class="sidebar-section-label">DATABASE</div>

                    <a class="nav-link {{ request()->routeIs('super-admin.database.control-center') ? 'active' : '' }}" href="{{ route('super-admin.database.control-center') }}">
                        <i class="bi bi-terminal"></i><span class="sidebar-label">Control Center</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.dashboard') ? 'active' : '' }}" href="{{ route('super-admin.database.dashboard') }}">
                        <i class="bi bi-speedometer2"></i><span class="sidebar-label">Dashboard</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.backups') ? 'active' : '' }}" href="{{ route('super-admin.database.backups') }}">
                        <i class="bi bi-cloud-upload"></i><span class="sidebar-label">Backups &amp; Recovery</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.health') ? 'active' : '' }}" href="{{ route('super-admin.database.health') }}">
                        <i class="bi bi-heart-pulse"></i><span class="sidebar-label">Health</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.integrity') ? 'active' : '' }}" href="{{ route('super-admin.database.integrity') }}">
                        <i class="bi bi-shield-check"></i><span class="sidebar-label">Integrity</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.performance') ? 'active' : '' }}" href="{{ route('super-admin.database.performance') }}">
                        <i class="bi bi-graph-up"></i><span class="sidebar-label">Performance</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.recovery') ? 'active' : '' }}" href="{{ route('super-admin.database.recovery') }}">
                        <i class="bi bi-arrow-repeat"></i><span class="sidebar-label">Disaster Recovery</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('super-admin.database.audit') ? 'active' : '' }}" href="{{ route('super-admin.database.audit') }}">
                        <i class="bi bi-journal-text"></i><span class="sidebar-label">Audit Logs</span>
                    </a>

                    {{-- ═══ SYSTEM / TOOLS ═══ --}}
                    <div class="sidebar-section-label">SYSTEM</div>

                    <a class="nav-link {{ request()->routeIs('admin.artisan-commands.*') ? 'active' : '' }}" href="{{ route('admin.artisan-commands.index') }}">
                        <i class="bi bi-terminal-fill"></i><span class="sidebar-label">Artisan Commands</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.deploy.*') ? 'active' : '' }}" href="{{ route('admin.deploy.index') }}">
                        <i class="bi bi-rocket-takeoff-fill"></i><span class="sidebar-label">Deploy</span>
                    </a>
                @endauth
            @endif
        </nav>

        <div class="dropdown dropup sidebar-user-card">
            <button type="button" class="sidebar-user-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-wrap">
                    <span class="avatar-circle avatar-initials">{{ strtoupper(substr($user->email ?? $roleLabel, 0, 1)) }}</span>
                    {{-- Live browser online/offline signal, sized/positioned exactly
                         where the old static green presence dot used to sit. --}}
                    <x-connectivity-signal :size="11.2" :wrap="11.2" class="cs-avatar-corner" />
                </div>
                <div class="sidebar-user-meta">
                    <span class="sidebar-user-name">{{ $roleLabel }}</span>
                    @if ($accountTypeLabel)
                        <span class="badge role-badge mt-1 {{ $user?->isOwnerAccount() ? 'bg-success text-white' : 'bg-info-subtle text-dark border' }}">{{ $accountTypeLabel }}</span>
                    @else
                        <span class="badge role-badge bg-light text-dark border mt-1">{{ $roleLabel }}</span>
                    @endif
                </div>
                <i class="bi bi-chevron-down sidebar-user-caret"></i>
            </button>
            <button type="button" class="sidebar-user-link" data-bs-toggle="dropdown" aria-expanded="false" title="{{ $roleLabel }}">
                <div class="avatar-wrap">
                    <span class="avatar-circle avatar-initials">{{ strtoupper(substr($user->email ?? $roleLabel, 0, 1)) }}</span>
                    <x-connectivity-signal :size="11.2" :wrap="11.2" class="cs-avatar-corner" />
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($isInstituteStaff)
                    <li><a class="dropdown-item" href="{{ route('owner.profile') }}"><i class="bi bi-person me-2"></i>{{ mawa_e('sidebar.profile') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="bi bi-gear me-2"></i>{{ mawa_e('sidebar.settings') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('account.security') }}"><i class="bi bi-shield-lock me-2"></i>{{ mawa_e('security.title') }}</a></li>
                @else
                    @auth('platform_admin')
                        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear me-2"></i>{{ mawa_e('sidebar.settings') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.security') }}"><i class="bi bi-shield-lock me-2"></i>{{ mawa_e('security.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.modules.index') }}"><i class="bi bi-puzzle me-2"></i>Modules &amp; Packages</a></li>
                    @endauth
                @endif
                @if ($workspaceMemberships->isNotEmpty())
                    <li><hr class="dropdown-divider"></li>
                    <li class="workspace-switcher-block px-3 pt-1 pb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-semibold text-uppercase text-muted">{{ mawa_e('workspace.switcher_title') }}</span>
                            <a href="{{ route('workspace.picker') }}" class="small">{{ mawa_e('workspace.manage') }}</a>
                        </div>
                        @foreach ($workspaceMemberships as $wsMembership)
                            @php
                                $isActive = $wsMembership->institution_id === $workspaceActiveId;
                            @endphp
                            <form method="POST" action="{{ route('workspace.switch', $wsMembership->institution_id) }}" class="mb-1">
                                @csrf
                                <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between gap-2 {{ $isActive ? 'active' : '' }}">
                                    <span>
                                        <span class="d-block fw-semibold">{{ $wsMembership->institution?->name ?? '#' . $wsMembership->institution_id }}</span>
                                        <span class="small text-muted d-block">{{ mawa_lang('workspace.branch', ['name' => $wsMembership->branch?->name ?? mawa_lang('workspace.all_branches')]) }}</span>
                                        <span class="small text-muted d-block">{{ $wsMembership->role?->name ?? $wsMembership->role_id }}</span>
                                    </span>
                                    @if ($isActive)
                                        <i class="bi bi-check-lg text-primary"></i>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>{{ mawa_e('logout') }}
                        </button>
                    </form>
                </li>
        </ul>
        </div>
    </aside>

    <main class="content">

        <div class="skeleton-loader" id="monetixSkeletonLoader" aria-hidden="true">
            <div class="sk-content">
                <div class="sk sk-line sk-title"></div>
                <div class="sk sk-line sk-subtitle"></div>
                <div class="sk-card">
                    <div class="sk sk-line"></div>
                    <div class="sk sk-line"></div>
                    <div class="sk sk-line"></div>
                    <div class="sk sk-line short"></div>
                </div>
                <div class="sk-card">
                    <div class="sk sk-line"></div>
                    <div class="sk sk-line"></div>
                    <div class="sk sk-line short"></div>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success" data-auto-dismiss>
                <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
            </div>
        @endif

        @if ($errors->any() && !session('photo_upload_error'))
            <div class="alert alert-danger" data-auto-dismiss>
                <i class="bi bi-exclamation-triangle-fill"></i> {{ mawa_lang('students.error_title') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" data-auto-dismiss>
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            </div>
        @endif

        @yield('content')

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
<script src="{{ asset('js/password-toggle.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/password-toggle.js')) }}"></script>
<script src="{{ asset('js/password-policy.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/password-policy.js')) }}"></script>
<script src="{{ asset('js/ajax.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/ajax.js')) }}"></script>
<script src="{{ asset('js/ajax-table.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/ajax-table.js')) }}"></script>
<script src="{{ asset('js/page-nav.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/page-nav.js')) }}"></script>
<script src="{{ asset('js/column-filters.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/column-filters.js')) }}"></script>
<script src="{{ asset('js/geo-select.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/geo-select.js')) }}"></script>
<script src="{{ asset('js/popup-fix.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/popup-fix.js')) }}"></script>
{{-- Alpine.js (global) — used by <x-connectivity-signal />. If you move Alpine into
     the @vite build instead, import 'alpinejs' and Alpine.start() in resources/js.
     Passing the page defer-safely: if this ever fails to load the component simply
     stays on its inert/stable visual state with no console errors. --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script>
(function () {
    var root = document.documentElement;
    var btn  = document.getElementById('monetixDarkToggle');
    var icon = document.getElementById('monetixDarkIcon');
    if (!btn || !icon) { return; }

    function isDark() {
        return root.classList.contains('monetix-dark') ||
               root.getAttribute('data-bs-theme') === 'dark';
    }
    function apply(enabled) {
        root.classList.toggle('monetix-dark', enabled);
        root.setAttribute('data-bs-theme', enabled ? 'dark' : 'light');
        icon.className = enabled ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        root.dataset.userTheme = enabled ? 'dark' : 'light';
    }
    function persist(enabled) {
        fetch('{{ route('account.preferences.theme') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ theme: enabled ? 'dark' : 'light' })
        });
    }
    var saved = null;
    try { saved = localStorage.getItem('monetix_ui_dark_admin'); } catch (e) {}
    var serverTheme = root.dataset.userTheme || 'default';
    if (saved !== null && serverTheme === 'default') {
        apply(saved === '1');
        persist(saved === '1');
    } else if (serverTheme !== 'default') {
        apply(serverTheme === 'dark');
        icon.className = isDark() ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    } else {
        icon.className = isDark() ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }
    btn.addEventListener('click', function () {
        var enabled = !isDark();
        apply(enabled);
        persist(enabled);
    });
})();

(function () {
    var list = document.getElementById('monetixNotifList');
    if (!list) { return; }

    var dot = document.getElementById('monetixNotifDot');
    var readAllBtn = document.getElementById('monetixNotifReadAll');
    var readAllUrl = readAllBtn ? readAllBtn.getAttribute('data-read-all-url') : '';
    var token = '{{ csrf_token() }}';

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: body ? JSON.stringify(body) : '{}'
        });
    }

    function decrementDot(count) {
        if (!dot) { return; }
        var current = parseInt(dot.textContent, 10) || 0;
        var next = Math.max(0, current - count);
        if (next <= 0) {
            dot.classList.remove('show');
        } else {
            dot.textContent = next > 99 ? '99+' : next;
        }
    }

    list.addEventListener('click', function (e) {
        var item = e.target.closest('.notification-item');
        if (!item) { return; }

        var markReadUrl = item.getAttribute('data-mark-read-url');
        var wasUnread = item.classList.contains('unread');

        if (wasUnread) {
            item.classList.remove('unread');
            item.classList.add('read');
            decrementDot(1);
        }

        if (markReadUrl && wasUnread) {
            e.preventDefault();
            post(markReadUrl).finally(function () {
                window.location.href = item.getAttribute('href');
            });
        }
    });

    function hideDot() {
        if (!dot) { return; }
        dot.textContent = '0';
        dot.classList.remove('show');
        dot.style.display = 'none';
    }

    if (readAllBtn) {
        readAllBtn.addEventListener('click', function () {
            var items = list.querySelectorAll('.notification-item.unread');
            for (var i = 0; i < items.length; i++) {
                items[i].classList.remove('unread');
                items[i].classList.add('read');
            }
            hideDot();
            if (readAllUrl) {
                post(readAllUrl).then(function (res) {
                    if (!res.ok) { throw new Error('mark-all failed'); }
                    // success — keep dot hidden
                }).catch(function () {
                    // rollback optimistic UI on failure so user can retry
                    for (var j = 0; j < items.length; j++) {
                        items[j].classList.remove('read');
                        items[j].classList.add('unread');
                    }
                    if (dot) {
                        dot.style.display = '';
                        dot.classList.add('show');
                    }
                });
            }
        });
    }
})();

(function () {
    var btn = document.getElementById('monetixSidebarToggle');
    if (!btn) { return; }

    var root = document.documentElement;
    var backdrop = document.getElementById('monetixSidebarBackdrop');
    var COLLAPSE_KEY = 'monetix_sidebar_collapsed';
    var mobileQuery = window.matchMedia('(max-width: 768px)');

    function isMobile() {
        return mobileQuery.matches;
    }

    function updateBtn() {
        var i = btn.querySelector('i');
        if (i) {
            i.className = isMobile()
                ? (root.classList.contains('sidebar-open') ? 'bi bi-x' : 'bi bi-list')
                : 'bi bi-list';
        }
        btn.setAttribute('aria-expanded', isMobile()
            ? (root.classList.contains('sidebar-open') ? 'true' : 'false')
            : (root.classList.contains('sidebar-collapsed') ? 'false' : 'true'));
    }

    function syncLayoutMode() {
        if (isMobile()) {
            // Mobile uses the off-canvas drawer — ignore the desktop collapse state.
            root.classList.remove('sidebar-collapsed');
            closeDrawer();
        } else {
            var saved = null;
            try { saved = localStorage.getItem(COLLAPSE_KEY); } catch (e) {}
            if (saved === '1') {
                root.classList.add('sidebar-collapsed');
            }
            updateBtn();
        }
    }

    // Replay the label slide-in only when the drawer is expanded by the user —
    // never on page load.
    function animateLabels() {
        var labels = document.querySelectorAll('.sidebar .nav-link .sidebar-label');
        for (var i = 0; i < labels.length; i++) {
            labels[i].classList.remove('sidebar-label-anim');
            void labels[i].offsetWidth;
            labels[i].classList.add('sidebar-label-anim');
        }
    }

    // Restore the persisted desktop collapse state (skipped on mobile).
    syncLayoutMode();

    function openDrawer() {
        root.classList.add('sidebar-open');
        if (backdrop) { backdrop.classList.add('show'); }
        document.body.style.overflow = 'hidden';
        animateLabels();
        updateBtn();
    }

    function closeDrawer() {
        root.classList.remove('sidebar-open');
        if (backdrop) { backdrop.classList.remove('show'); }
        document.body.style.overflow = '';
        updateBtn();
    }

    btn.addEventListener('click', function () {
        if (isMobile()) {
            if (root.classList.contains('sidebar-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        } else {
            var wasCollapsed = root.classList.contains('sidebar-collapsed');
            root.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem(COLLAPSE_KEY, root.classList.contains('sidebar-collapsed') ? '1' : '0');
            } catch (e) {}
            if (wasCollapsed) { animateLabels(); }
            updateBtn();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }

    mobileQuery.addEventListener('change', syncLayoutMode);

    // Collapsed-mode avatar is now a dropdown toggle — keep the sidebar from
    // clipping the open card while the dropdown is showing.
    var userCard = document.querySelector('.sidebar-user-card');
    if (userCard) {
        var sidebar = userCard.closest('.sidebar');
        userCard.addEventListener('shown.bs.dropdown', function () {
            if (sidebar) { sidebar.classList.add('sidebar-no-clip'); }
        });
        userCard.addEventListener('hidden.bs.dropdown', function () {
            if (sidebar) { sidebar.classList.remove('sidebar-no-clip'); }
        });
    }

    updateBtn();
})();

(function () {
    var loader = document.getElementById('monetixSkeletonLoader');
    if (!loader) { return; }

    var MIN_TIME = 334;
    var MAX_TIME = 5000;
    var started = Date.now();
    var done = false;

    function hide() {
        if (done) { return; }
        done = true;
        loader.classList.add('hide');
        setTimeout(function () { loader.remove(); }, 300);
    }

    function tryHide() {
        var elapsed = Date.now() - started;
        if (elapsed >= MIN_TIME) {
            hide();
        } else {
            setTimeout(tryHide, MIN_TIME - elapsed);
        }
    }

    // Safety net: never leave the skeleton blocking the page.
    setTimeout(hide, MAX_TIME);

    if (document.readyState === 'complete') {
        tryHide();
    } else {
        window.addEventListener('load', tryHide);
    }
})();
</script>
<style>
    main.content { will-change: opacity; }
    main.content.seamless-fade { animation: seamlessIn .18s ease-out forwards; }
    @keyframes seamlessIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    /* Prevent layout shift/shake on tab switch */
    .nav-tabs { scrollbar-gutter: stable; }
    .admin-card, .page-header { transform: translateZ(0); backface-visibility: hidden; }
    /* Modal/backdrop/tost fixed-position containment fix (non-destructive):
       transform/will-change create containing blocks for position:fixed modals,
       trapping crop modal inside .content. Disable only while modal open. */
    body.modal-open main.content { will-change: auto !important; }
    body.modal-open .admin-card, body.modal-open .page-header { transform: none !important; }
</style>
<script>
(function () {
    if (!window.Monetix || !Monetix.loadPage) { return; }

    document.addEventListener('click', function (e) {
        var switcher = document.querySelector('[data-tab-switch]');
        if (!switcher || !switcher.contains(e.target)) { return; }
        var link = e.target.closest('a[href]');
        if (!link) { return; }
        var url = link.getAttribute('href');
        if (url && url !== location.href) {
            e.preventDefault();
            Monetix.loadPage(url);
        }
    });

    window.addEventListener('popstate', function () {
        // If the user was browsing pages inside a list (1 → 2 → 5), go back to
        // the previous page of that same list instead of leaving the list.
        var prev = window.Monetix && Monetix.navHistory
            ? Monetix.navHistory.previous(window.location.pathname)
            : null;
        Monetix.loadPage(prev || (window.location.pathname + window.location.search), { push: false });
    });
})();
</script>
@yield('scripts')
<div id="page-scripts">
    @stack('scripts')
</div>
<script>
// Global UID copy helper — also in resources/js/app.js (inline fallback for non-Vite pages)
if (typeof window.copyToClipboard !== 'function') {
window.copyToClipboard = function(text, button) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
            var original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(function(){ button.innerHTML = original; }, 2000);
        }).catch(function(){ fallbackCopy(text, button); });
    } else { fallbackCopy(text, button); }
    function fallbackCopy(val, btn){
        var input=document.createElement('input'); input.value=val; input.style.position='fixed'; input.style.opacity='0';
        document.body.appendChild(input); input.select(); try{ document.execCommand('copy'); }catch(e){} document.body.removeChild(input);
        var original=btn.innerHTML; btn.innerHTML='<i class="bi bi-check-lg text-success"></i>'; setTimeout(function(){ btn.innerHTML=original; },2000);
    }
};
}
</script>
</body>
</html>
