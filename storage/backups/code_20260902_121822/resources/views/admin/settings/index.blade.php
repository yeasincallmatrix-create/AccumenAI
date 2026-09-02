@extends('layouts.standalone')

@section('title', mawa_e('settings_page.title') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.title'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.title') }}</h4>
    <p>{{ mawa_e('settings_page.subtitle') }}</p>
</div>

<div class="settings-layout">

    <div class="settings-nav">
        <div class="settings-nav-title">{{ mawa_e('settings_page.title') }}</div>
        <button class="settings-nav-item settings-tab-btn active" type="button" data-target="pane-account" aria-selected="true">
            <i class="bi bi-person-gear"></i>
            <span>{{ mawa_e('settings_page.account') }}</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-staff" aria-selected="false">
            <i class="bi bi-person-plus-fill"></i>
            <span>{{ mawa_e('settings_page.staff_requests') }}</span>
            @if ($pendingStaffCount > 0)
                <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingStaffCount }}</span>
            @endif
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-appearance" aria-selected="false">
            <i class="bi bi-palette"></i>
            <span>{{ mawa_e('settings_page.appearance') }}</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-mail-payment" aria-selected="false">
            <i class="bi bi-envelope-paper"></i>
            <span>{{ mawa_e('settings_page.mail_payment') }}</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-ai" aria-selected="false">
            <i class="bi bi-robot"></i>
            <span>AI</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-security" aria-selected="false">
            <i class="bi bi-shield-lock"></i>
            <span>{{ mawa_e('settings_page.security') }}</span>
        </button>
    </div>

    <div class="settings-content">
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <span><i class="bi bi-info-circle me-2"></i>SMS Provider &amp; Phone OTP are now in <strong>Configuration Center</strong> — platform-global Super Admin settings.</span>
            <span class="d-flex gap-2">
                <a href="{{ route('admin.platform-settings.index') }}#pane-sms" class="btn btn-sm btn-primary"><i class="bi bi-chat-dots me-1"></i> SMS Provider</a>
                <a href="{{ route('admin.platform-settings.index') }}#pane-otp" class="btn btn-sm btn-outline-primary"><i class="bi bi-phone me-1"></i> Phone OTP</a>
            </span>
        </div>
        <div class="admin-card settings-options-card">
            <div class="settings-pane active" id="pane-account">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-person-gear"></i> Account</div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $admin->name ?? 'Yasin Sheikh' }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $admin->email }}</dd>
                    <dt class="col-sm-4">Role</dt><dd class="col-sm-8">{{ $roleLabel }}</dd>
                </dl>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-key"></i> {{ mawa_e('settings_page.change_password') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.password') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Update Password</button>
                </form>

                <hr>

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

            <div class="settings-pane" id="pane-staff">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-person-plus-fill"></i> Staff Registration Requests ({{ $pendingStaff->count() }})</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Institute</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registered</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingStaff as $staff)
                                <tr>
                                    <td class="fw-semibold">{{ $staff->name }}</td>
                                    <td>{{ $staff->institute->name ?? '—' }}</td>
                                    <td>{{ $staff->email }}</td>
                                    <td>{{ $staff->phone }}</td>
                                    <td>{{ $staff->created_at->format('d M Y H:i') }}</td>
                                    <td class="text-end">
                                        <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check-lg"></i> Approve</button>
                                        </form>
                                        <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}"
                                              onsubmit="return confirm('Reject registration for {{ $staff->name }}?');">
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i> Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No pending staff registrations.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="settings-pane" id="pane-appearance">
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

            <div class="settings-pane" id="pane-mail-payment">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-envelope"></i> SMTP</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="{{ $smtpHost }}" placeholder="smtp.gmail.com">
                            @error('smtp_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_port">SMTP Port</label>
                            <input type="text" id="smtp_port" name="smtp_port" class="form-control" value="{{ $smtpPort }}" placeholder="587">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                                <option value="none" {{ $smtpEncryption === 'none' ? 'selected' : '' }}>None</option>
                                <option value="tls" {{ $smtpEncryption === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ $smtpEncryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="{{ $smtpUsername }}" placeholder="you@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="" placeholder="{{ $smtpPasswordMasked ?? '••••••••' }}">
                            <div class="form-text small text-muted">{{ ($smtpConfigured ?? false) ? 'Leave blank to keep existing password' : 'Not configured' }}</div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
                </form>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-credit-card"></i> {{ mawa_e('settings_page.payment_heading') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="payment_gateway">Payment Gateway</label>
                            <input type="text" id="payment_gateway" name="payment_gateway" class="form-control" value="{{ $paymentGateway }}" placeholder="bKash / Nagad / Stripe">
                            @error('payment_gateway')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
                </form>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-send"></i> {{ mawa_e('settings_page.test_email_heading') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.test') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="test_email">Recipient</label>
                            <input type="email" id="test_email" name="test_email" class="form-control" value="{{ $admin->email }}" placeholder="you@example.com">
                            @error('smtp_test')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i> Save & Test</button>
                </form>
            </div>

            <div class="settings-pane" id="pane-ai">
                @include('admin.settings._ai', ['aiEmbedded' => true])
            </div>

            <div class="settings-pane" id="pane-security">
                @include('security._panel')
            </div>

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.settings-tab-btn'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('.settings-pane'));

    function activate(id) {
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-target') === id;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (pane) {
            pane.classList.toggle('active', pane.id === id);
        });
        if (history.replaceState) {
            history.replaceState(null, '', '#' + id);
        }
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activate(btn.getAttribute('data-target'));
        });
    });

    var hash = window.location.hash;
    if (hash && panes.some(function (p) { return '#' + p.id === hash; })) {
        activate(hash.slice(1));
    }
})();

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
</script>
@endpush
