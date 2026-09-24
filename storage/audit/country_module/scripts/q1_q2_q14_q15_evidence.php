<?php
$out = [];
// Q1 controllers
$out['q1_controllers'] = [];
foreach (['InstituteCreationController', 'InstituteOnboardingController'] as $c) {
    $path = app_path("Http/Controllers/{$c}.php");
    $out['q1_controllers'][$c] = file_exists($path) ? 'EXISTS' : 'MISSING';
}
// country mentions in onboarding
$onb = app_path('Http/Controllers/InstituteOnboardingController.php');
if (file_exists($onb)) {
    $lines = file($onb);
    $hits = [];
    foreach ($lines as $i => $l) {
        if (preg_match('/country/i', $l)) {
            $hits[] = ($i + 1) . ': ' . rtrim($l);
        }
    }
    $out['q1_onboarding_country_hits'] = $hits;
}
// Q14 middleware country/geo
$mw = [];
foreach (glob(app_path('Http/Middleware/*.php')) as $f) {
    $hits = [];
    foreach (file($f) as $i => $l) {
        if (preg_match('/country|geo/i', $l)) {
            $hits[] = ($i + 1) . ': ' . rtrim($l);
        }
    }
    if ($hits) {
        $mw[basename($f)] = $hits;
    }
}
$out['q14_middleware_country_geo'] = $mw;
// Q15 route files with country/geo
$routes = [];
foreach (glob(base_path('routes/*.php')) as $f) {
    $hits = [];
    foreach (file($f) as $i => $l) {
        if (preg_match('/country|geo/i', $l)) {
            $hits[] = ($i + 1) . ': ' . rtrim($l);
        }
    }
    if ($hits) {
        $routes[basename($f)] = $hits;
    }
}
$out['q15_routes_country_geo'] = $routes;
file_put_contents(storage_path('audit/country_module/q1_q2_q14_q15_raw.txt'), print_r($out, true));
echo "OK\n";
