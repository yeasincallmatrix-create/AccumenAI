{{-- Seamless Payroll 5-tab strip — like HR 4-tab, include on Payroll, Performance, Salary Structure, Reconciliation, Training --}}
<ul class="nav nav-tabs mb-4" role="tablist" data-tab-switch>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.payroll.periods.*') ? 'active' : '' }}" href="{{ route('hr.payroll.periods.index') }}"><i class="bi bi-cash-coin me-1"></i>Payroll</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.performance.*') ? 'active' : '' }}" href="{{ route('hr.performance.dashboard') }}"><i class="bi bi-graph-up-arrow me-1"></i>Performance</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.salary-structures.*') ? 'active' : '' }}" href="{{ route('hr.salary-structures.index') }}"><i class="bi bi-wallet2 me-1"></i>Salary Structure</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.payroll.reconciliation') ? 'active' : '' }}" href="{{ route('hr.payroll.reconciliation') }}"><i class="bi bi-arrow-left-right me-1"></i>Reconciliation</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.training.*') ? 'active' : '' }}" href="{{ route('hr.training.dashboard') }}"><i class="bi bi-mortarboard-fill me-1"></i>Training</a>
    </li>
</ul>
