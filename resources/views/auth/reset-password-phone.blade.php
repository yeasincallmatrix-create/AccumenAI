<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Reset Password (Phone)</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary">
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="card p-4">
<h3>Set New Password</h3>
@if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ route('password.phone.update') }}">@csrf
<input type="hidden" name="phone" value="{{ $phone }}">
<div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
<button class="btn btn-primary w-100" type="submit">Reset Password</button>
</form>
</div></div></div></div>
</body>
</html>
