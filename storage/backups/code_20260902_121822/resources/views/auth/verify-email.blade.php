<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_e('auth.verify_email_title') }} — AccumenAI</title>
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
            <div class="col-md-6 col-lg-5">
                <div class="auth-card mb-3 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    <h1 class="auth-title h3 mb-2">{{ mawa_e('auth.verify_email_title') }}</h1>

                    @if (session('status'))
                        <div class="alert alert-success py-2" data-auto-dismiss><i class="bi bi-check-circle-fill me-1"></i>{{ session('status') }}</div>
                    @endif

                    <p class="auth-subtitle mb-3">{{ mawa_e('auth.verify_email_hint') }}</p>
                    <p class="small text-body-secondary mb-4">{{ request()->user()?->email }}</p>

                    <div id="resend-status" class="mb-3" style="display:none"></div>
                    <button id="resend-btn" class="btn btn-primary auth-btn w-100" type="button">
                        <i class="bi bi-envelope-arrow-up"></i> {{ mawa_e('auth.resend_verification') }}
                    </button>

                    <p class="auth-switch mt-4 mb-0">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link text-danger p-0 border-0">{{ mawa_e('auth.logout_and_login_again') }}</button>
                        </form>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
<script>
(function () {
    var btn = document.getElementById('resend-btn');
    var status = document.getElementById('resend-status');
    if (!btn || !status) return;

    var cooldown = 60;
    var timer = null;
    var sending = false;

    function setCooldown(seconds) {
        cooldown = seconds;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Resend available in ' + cooldown + 's';
        timer = setInterval(function () {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                timer = null;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-envelope-arrow-up"></i> {{ mawa_e("auth.resend_verification") }}';
                status.style.display = 'none';
            } else {
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Resend available in ' + cooldown + 's';
            }
        }, 1000);
    }

    btn.addEventListener('click', function () {
        if (sending || btn.disabled) return;
        sending = true;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
        status.style.display = 'none';

        fetch('{{ route("verification.send") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
        .then(function (res) {
            sending = false;
            if (res.ok && res.data.success) {
                status.className = 'alert alert-success py-2 mb-3';
                status.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>' + (res.data.message || 'Verification email sent.');
                status.style.display = 'block';
                setCooldown(60);
            } else if (res.status === 429) {
                status.className = 'alert alert-warning py-2 mb-3';
                status.innerHTML = '<i class="bi bi-clock-history me-1"></i> Too many requests. Please try again later.';
                status.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-envelope-arrow-up"></i> {{ mawa_e("auth.resend_verification") }}';
            } else {
                status.className = 'alert alert-danger py-2 mb-3';
                status.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Something went wrong. Please try again.';
                status.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-envelope-arrow-up"></i> {{ mawa_e("auth.resend_verification") }}';
            }
        })
        .catch(function () {
            sending = false;
            status.className = 'alert alert-danger py-2 mb-3';
            status.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Network error. Please check your connection and try again.';
            status.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-envelope-arrow-up"></i> {{ mawa_e("auth.resend_verification") }}';
        });
    });
})();
</script>
</body>
</html>