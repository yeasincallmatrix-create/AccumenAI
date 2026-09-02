<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    @include('auth.partials.register-progress', ['step' => 2])
                    <h1 class="auth-title h4 mb-1 text-center">Verify Your Email</h1>
                    <p class="auth-subtitle mb-4 text-center text-muted small">We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. It expires in 10 minutes.</p>

                    @if (session('status'))
                        <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.otp.verify') }}" id="otp-form">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="otp">Verification Code <span class="text-danger">*</span></label>
                            <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code" class="form-control form-control-lg text-center fs-4 tracking-widest" name="otp" placeholder="Enter 6-digit code" required maxlength="8" autofocus>
                            @if($expiresAt)
                                <div class="form-text">Expires at {{ $expiresAt->format('H:i') }} ({{ $expiresAt->diffForHumans() }})</div>
                            @endif
                        </div>
                        <button class="btn btn-primary w-100" type="submit" id="verify-btn">
                            <i class="bi bi-check-circle"></i> Verify Email
                        </button>
                    </form>

                    <form method="POST" action="{{ route('register.otp.resend') }}" class="mt-3" id="resend-form">
                        @csrf
                        <button class="btn btn-outline-secondary w-100" type="submit" id="resend-btn" @if(($cooldown ?? 0) > 0) disabled @endif>
                            <i class="bi bi-arrow-repeat"></i> Resend Code @if(($cooldown ?? 0) > 0) (<span id="countdown">{{ $cooldown }}</span>s) @endif
                        </button>
                        <div class="form-text text-center mt-1">Cooldown 60s • Max 5 per hour • 5 attempts max</div>
                    </form>

                    <p class="auth-switch mt-4 text-center">
                        <a href="{{ route('register.account') }}">Change email</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var btn = document.getElementById('resend-btn');
  var cd = document.getElementById('countdown');
  if(!btn || !cd) return;
  var sec = parseInt(cd.textContent||'0',10);
  if(sec<=0) return;
  var interval = setInterval(function(){
    sec--;
    cd.textContent = sec;
    if(sec<=0){ clearInterval(interval); btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Resend Code'; }
  },1000);
  // Prevent double submit
  var form=document.getElementById('otp-form');
  form.addEventListener('submit', function(){ document.getElementById('verify-btn').disabled=true; });
})();
</script>
</body>
</html>
