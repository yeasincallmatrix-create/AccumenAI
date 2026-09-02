<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_lang('workspace.picker_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    <h1 class="auth-title h3 mb-1 text-center">{{ mawa_lang('workspace.picker_title') }}</h1>
                    <p class="auth-subtitle mb-4 text-center">{{ mawa_lang('workspace.picker_hint') }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    @php $canCreateBusiness = auth('web')->user()?->isOwnerAccount() ?? false; @endphp
                    @if($canCreateBusiness)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-muted fw-semibold"><i class="bi bi-buildings"></i> Your Workspaces</span>
                            <a href="{{ route('workspace.onboarding') }}" class="btn btn-primary btn-sm px-3" id="add-business-btn">
                                <i class="bi bi-plus-lg me-1"></i> Add Business
                            </a>
                        </div>
                    @endif

                    @forelse ($memberships as $membership)
                        <form method="POST" action="{{ route('workspace.switch', $membership->institution_id) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100 text-start py-3 {{ $membership->institution_id === $activeId ? 'active' : '' }}">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold">{{ $membership->institution?->name ?? '#' . $membership->institution_id }}</div>
                                        <div class="small text-secondary">{{ mawa_lang('workspace.branch', ['name' => $membership->branch?->name ?? mawa_lang('workspace.all_branches')]) }}</div>
                                        <div class="small text-secondary">{{ $membership->role?->name ?? $membership->role_id }}</div>
                                    </div>
                                    <i class="bi bi-box-arrow-in-right"></i>
                                </div>
                            </button>
                        </form>
                    @empty
                        <div class="alert alert-warning">{{ mawa_lang('workspace.picker_empty') }}</div>
                    @endforelse

                    @if($canCreateBusiness)
                        <div class="mt-3 pt-3 border-top">
                            <a href="{{ route('workspace.onboarding') }}" class="btn btn-outline-primary w-100 py-2">
                                <i class="bi bi-building-add me-1"></i> Add Business — Prepare New Workspace
                            </a>
                            <div class="form-text text-center mt-1">Same as after OTP verification: pick country, industry &amp; create workspace</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm text-secondary"><i class="bi bi-box-arrow-right"></i> {{ mawa_e('auth.sign_out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>