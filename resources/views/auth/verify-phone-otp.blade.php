<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Verify OTP</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary">
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="card p-4">
<h3>Verify OTP</h3>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ route('password.phone.verify') }}">@csrf
<input type="hidden" name="phone" value="{{ $phone }}">
<div class="mb-3"><label class="form-label">OTP</label><input type="text" name="otp" class="form-control" required maxlength="6"></div>
<button class="btn btn-primary w-100" type="submit">Verify</button>
</form>
</div></div></div></div>
</body>
</html>
