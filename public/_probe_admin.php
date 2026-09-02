<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\PlatformAdmin;
use App\Models\Theme;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

Auth::shouldUse('platform_admin');
$admin = PlatformAdmin::find(1);
Auth::loginUsingId(1);

echo 'theme_id pref='.var_export($admin->preference('theme_id'), true)."\n";
$t = Theme::where('status', 'active')->find($admin->preference('theme_id'));
echo 'theme found='.($t ? $t->name.' '.$t->primary_color : 'null')."\n";

// Force a composer callback run by rendering a view
view('layouts.partials.theme_colors', ['themePrimary' => null, 'themeSecondary' => null])->render();

$data = View::shared('themePrimary');
$data2 = View::shared('themeSecondary');
echo 'shared themePrimary='.var_export($data, true)."\n";
echo 'shared themeSecondary='.var_export($data2, true)."\n";
echo 'shared activeColorTheme='.(View::shared('activeColorTheme') ? View::shared('activeColorTheme')->name : 'null')."\n";
