<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\DashboardController;
use App\Models\PlatformAdmin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

View::share('errors', new ViewErrorBag);

$admin = PlatformAdmin::find(1);
Auth::shouldUse('platform_admin');
Auth::loginUsingId(1);

$request = Request::create('/');
$request->setUserResolver(fn () => $admin);
$request->setLaravelSession(app('session')->driver());

try {
    $html = app(DashboardController::class)($request)->render();
} catch (Throwable $e) {
    echo 'dashboard render FAIL: '.$e->getMessage()."\n";
    exit;
}

preg_match('#<style>(.*?)</style>#s', $html, $m);
if (! $m) {
    echo "NO STYLE BLOCK FOUND\n";
} else {
    echo 'bs-primary='.(preg_match('/--bs-primary: (\#[0-9A-Fa-f]{6})/', $m[1], $x) ? $x[1] : 'NOT SET')."\n";
    echo 'dropdown-link-active-bg='.(preg_match('/--bs-dropdown-link-active-bg: (\#[0-9A-Fa-f]{6})/', $m[1], $x) ? $x[1] : 'NOT SET')."\n";
}

preg_match('/class="btn btn-sm btn-outline-primary[^"]*"/', $html, $btn);
echo 'filter-trigger-class='.($btn[0] ?? 'NOT FOUND')."\n";
