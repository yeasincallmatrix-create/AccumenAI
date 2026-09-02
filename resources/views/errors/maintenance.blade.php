<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Maintenance — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container text-center">
    <div class="card mx-auto" style="max-width:560px">
        <div class="card-body p-5">
            <h2 class="mb-3">Maintenance Mode</h2>
            <p class="text-muted">{{ $message ?? 'The platform is under maintenance. Please try again shortly.' }}</p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-2">Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
