<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.tds.*') ? 'active' : '' }}"
           href="{{ route('settings.tds.index') }}">Deductions</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.tds-receivable.*') ? 'active' : '' }}"
           href="{{ route('settings.tds-receivable.index') }}">Receivable</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.tds-certificates-received.*') ? 'active' : '' }}"
           href="{{ route('settings.tds-certificates-received.index') }}">Certificates Received</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.corporate-tax.*') ? 'active' : '' }}"
           href="{{ route('settings.corporate-tax.index') }}">Corporate Tax</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.tax-reports.*') ? 'active' : '' }}"
           href="{{ route('settings.tax-reports.tds-summary') }}">Reports</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.tax-reconciliation.*') ? 'active' : '' }}"
           href="{{ route('settings.tax-reconciliation.index') }}">Reconciliation</a>
    </li>
</ul>
