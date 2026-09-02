<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password (Phone) — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <h3>Reset via Phone</h3>
                @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                <form method="POST" action="{{ route('password.phone.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Send OTP</button>
                </form>
                <p class="mt-3"><a href="{{ route('password.request') }}">Use email instead</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
