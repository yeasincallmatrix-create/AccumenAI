{{-- Seamless HR 4-tab strip — include on HR, Employees, Attendance, Leave (navbar stays full) --}}
<ul class="nav nav-tabs mb-4" role="tablist" data-tab-switch>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}" href="{{ route('hr.dashboard') }}"><i class="bi bi-grid-fill me-1"></i>HR</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}" href="{{ route('hr.employees.index') }}"><i class="bi bi-people-fill me-1"></i>Employees</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}" href="{{ route('hr.attendance.dashboard') }}"><i class="bi bi-calendar-check-fill me-1"></i>Attendance</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}" href="{{ route('hr.leave.dashboard') }}"><i class="bi bi-calendar-week-fill me-1"></i>Leave</a>
    </li>
</ul>
