@extends('layouts.standalone')

@section('title', 'Page Expired — 419')
@section('page_title', 'Page Expired')

@section('content')
@php
    $currentUserEmail = null;
    try { $currentUserEmail = auth('institute_user')->user()?->email ?? auth('web')->user()?->email ?? auth('platform_admin')->user()?->email ?? auth('guardian')->user()?->email; } catch (\Throwable $_) {}
    $isAdminContext = request()->is('admin*') || str_contains((string) request()->headers->get('referer',''), '/admin');
    $isGuardianContext = request()->is('guardian*') || str_contains((string) request()->headers->get('referer',''), '/guardian');
    $loginRoute = $isAdminContext ? route('admin.login') : ($isGuardianContext ? route('guardian.login') : route('login'));
    // Non-destructive: do not leak a hard-coded email; show current user if available
@endphp
<div class="text-center py-4">
    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10" style="width:72px;height:72px;">
        <i class="bi bi-clock-history" style="font-size:2rem;color:#ffc107;"></i>
    </div>
    <h3 class="h5 fw-bold">Page Expired (419)</h3>
    <p class="text-muted mx-auto" style="max-width:480px;">
        Your session expired or the form was idle too long. This happens if the login page was open for a while, cookies are blocked, or you used the browser back button.
    </p>
    <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="{{ url()->current() }}" class="btn btn-primary rounded-pill px-4" onclick="location.reload(); return false;"><i class="bi bi-arrow-clockwise me-1"></i> Refresh & Try Again</a>
        <a href="{{ $loginRoute }}" class="btn btn-outline-secondary rounded-pill px-4">Go to Login</a>
        {{-- Non-destructive fallback: logout via GET works even when CSRF expired --}}
        <a href="{{ route('logout.get') }}" class="btn btn-outline-danger rounded-pill px-4"><i class="bi bi-box-arrow-right me-1"></i> Log out & re-login</a>
    </div>
    <div class="mt-4 text-start mx-auto" style="max-width:520px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body small">
                <div class="fw-semibold mb-2"><i class="bi bi-tools me-1"></i> Quick fix @if($currentUserEmail) for {{ $currentUserEmail }} @endif</div>
                <ol class="mb-2 ps-3">
                    <li>Hard refresh login page: <code>Ctrl + F5</code> (or <code>Cmd+Shift+R</code> on Mac)</li>
                    <li>Clear cookies for this site & try in Incognito/Private window</li>
                    <li>Make sure cookies are enabled and system time is correct</li>
                    <li>Use the correct portal:
                        <br><span class="badge bg-light text-dark border">User:</span> <code>{{ url('/login') }}</code>
                        <br><span class="badge bg-light text-dark border">Admin:</span> <code>{{ url('/admin/login') }}</code>
                        <br><span class="badge bg-light text-dark border">Guardian:</span> <code>{{ url('/guardian/login') }}</code>
                    </li>
                    <li>Do not leave the login form open &gt; 2 hours (session lifetime 120 min) before submitting</li>
                </ol>
                @if($currentUserEmail)
                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Your account <strong>{{ $currentUserEmail }}</strong> session expired. Use <em>Refresh</em> or <em>Log out & re-login</em> above. If it persists, contact admin to reset session.
                </div>
                @else
                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Your session expired. Please refresh or <a href="{{ $loginRoute }}">go to login</a>. If it persists, clear cookies or try Incognito.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
