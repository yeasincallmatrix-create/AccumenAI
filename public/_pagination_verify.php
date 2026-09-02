<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

View::share('errors', new ViewErrorBag);

Auth::shouldUse('platform_admin');
Auth::loginUsingId(1);

$html = view('admin.courses.index', [
    'courses' => Course::with(['category', 'subjects'])->withCount('batches')->whereNull('deleted_at')->orderBy('name')->paginate(2),
    'subjects' => Subject::with(['institute', 'category'])->whereNull('deleted_at')->orderBy('name')->paginate(2),
])->render();

if (preg_match('/<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">.*?<\/div>/s', $html, $m)) {
    echo "WRAPPER:\n".$m[0]."\n\n";
}
echo 'components-loaded='.(strpos($html, 'components.css') !== false ? 'yes' : 'no')."\n";
if (preg_match('/href="([^"]*components\.css[^"]*)"/', $html, $m)) {
    echo 'components-css-href='.$m[1]."\n";
}
