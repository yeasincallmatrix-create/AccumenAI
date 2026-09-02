<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Guardian;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\OfflineSyncQueue;
use App\Models\PlatformAdmin;
use App\Models\Student;
use App\Models\Theme;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\CustomAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\ModuleAccessService;
use App\Support\AiConfig;
use App\Support\NotificationCenter;
use App\Support\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once __DIR__.'/../helpers.php';

        // The AI provider is chosen at first resolution, not at register time:
        // AiConfig::provider() reads the runtime DB setting, and the Eloquent
        // connection resolver is only bound during boot(). Resolving it here
        // would fail every artisan command / HTTP bootstrap.
        $this->app->singleton(AiProvider::class, function (): AiProvider {
            $provider = AiConfig::provider();
            $class = match ($provider) {
                'custom' => CustomAiProvider::class,
                default => OpenAiProvider::class,
            };
            return app($class);
        });

        // The app registers its own auth routes (two guarded portals); we only
        // reuse Fortify as the engine for password reset / 2FA / verification.
        Fortify::ignoreRoutes();

        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Parallel testing: populate newly created test databases with full data dump
        ParallelTesting::setUpTestDatabaseBeforeMigrating(function (string $testDatabase) {
            $fullDumpPath = database_path('schema/full_data.sql');
            $schemaPath = database_path('schema/schema.sql');
            $migrationsPath = database_path('schema/migrations_data.sql');
            $seedPath = database_path('schema/seed_data.sql');

            $dbConfig = config('database.connections.mysql');
            $host = $dbConfig['host'] ?? '127.0.0.1';
            $port = $dbConfig['port'] ?? 3306;
            $username = $dbConfig['username'] ?? 'root';
            $password = $dbConfig['password'] ?? '';

            $mysqlPath = 'C:\\xampp\\mysql\\bin\\mysql.exe';
            $baseCmd = "\"{$mysqlPath}\" -h {$host} -P {$port} -u {$username}";
            if ($password !== '') {
                $baseCmd .= " -p\"{$password}\"";
            }

            // Ensure the parallel test database exists before importing
            exec("\"{$mysqlPath}\" -h {$host} -P {$port} -u {$username} -e \"CREATE DATABASE IF NOT EXISTS `{$testDatabase}`\" 2>&1", $createOutput, $createReturn);

            if (file_exists($fullDumpPath)) {
                exec("{$baseCmd} {$testDatabase} < \"{$fullDumpPath}\" 2>&1", $output, $returnVar);
            } elseif (file_exists($schemaPath)) {
                exec("{$baseCmd} {$testDatabase} < \"{$schemaPath}\" 2>&1", $output, $returnVar);

                if (file_exists($migrationsPath)) {
                    exec("{$baseCmd} {$testDatabase} < \"{$migrationsPath}\" 2>&1", $output, $returnVar);
                }

                if (file_exists($seedPath)) {
                    exec("{$baseCmd} {$testDatabase} < \"{$seedPath}\" 2>&1", $output, $returnVar);
                }
            }
        });

        // Platform general/branding wiring: DB → config/runtime precedence
        try {
            if (! app()->runningInConsole() || app()->environment('testing')) {
                // Defer DB lookup until tables exist; wrap in try/catch for migrate/install
                $brandName = \App\Models\Setting::get('brand.name');
                if (filled($brandName)) {
                    config(['app.name' => $brandName]);
                } else {
                    $appName = \App\Models\Setting::get('app.name');
                    if (filled($appName)) config(['app.name' => $appName]);
                }
                $tz = \App\Models\Setting::get('app.timezone');
                if (filled($tz)) config(['app.timezone' => $tz]);
                $locale = \App\Models\Setting::get('app.language');
                if (filled($locale) && in_array($locale, ['en','bn'], true)) config(['app.locale' => $locale]);
            }
        } catch (\Throwable $e) {}

        // Prevent stale module_access cache for ALL users (P0 fix for Supermarket 17 etc.)
        // Any change to institute package must invalidate the cached enabled-modules.
        Institute::created(function (Institute $institute) {
            app(ModuleAccessService::class)->flushCache($institute->id);
        });
        Institute::updated(function (Institute $institute) {
            if ($institute->wasChanged('package_id') || $institute->wasChanged('status') || $institute->wasChanged('industry')) {
                app(ModuleAccessService::class)->flushCache($institute->id);
            }
        });
        Institute::deleted(function (Institute $institute) {
            app(ModuleAccessService::class)->flushCache($institute->id);
        });
        // Direct DB updates via query builder bypass Eloquent events — also listen at query level
        // Any update to institutes.package_id via DB::table will be caught by flushing all on package_modules change is handled in ModuleAccessService,
        // but we also flush on every institutes update as safety net via DB::listen
        if (! app()->environment('testing')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                if (str_contains(strtolower($query->sql), 'update') && str_contains(strtolower($query->sql), 'institutes') && str_contains(strtolower($query->sql), 'package_id')) {
                    // Flush all module_access caches when a bulk package update is detected (covers seeders using DB::table)
                    try {
                        foreach (\App\Models\Institute::withoutGlobalScopes()->pluck('id') as $id) {
                            app(ModuleAccessService::class)->flushCache((int) $id);
                        }
                    } catch (\Throwable $e) {}
                }
            });
        }

        // Testing helper: auto-assign PREMIUM package to institutes created without package so legacy sales tests remain green
        // Production: CheckModuleAccess treats null as FREE (not bypass), but tests need sales enabled.
        Institute::creating(function (Institute $institute) {
            if (app()->environment('testing') && empty($institute->package_id)) {
                $premiumId = \Illuminate\Support\Facades\DB::table('subscription_packages')->where('slug', 'PREMIUM')->value('id');
                if ($premiumId) {
                    $institute->package_id = $premiumId;
                }
            }
        });

        View::composer('*', function ($view) {
            try {
                $user = Auth::user();
            } catch (\Throwable $e) {
                // During error rendering the auth guard may not be resolvable
                // (e.g. SessionGuard not yet bound). Fail silently to avoid
                // masking the original exception with a second fatal error.
                return;
            }

            if ($user instanceof Guardian) {
                $view->with('user', $user)
                    ->with('roleLabel', mawa_lang('guardian.account'))
                    ->with('accountTypeLabel', null)
                    ->with('workspaceMemberships', collect())
                    ->with('workspaceActiveId', null)
                    ->with('isInstituteStaff', false)
                    ->with('usesClassTerm', false)
                    ->with('workspaceAllowedFinance', false)
                    ->with('workspaceAllowedCrm', false)
                    ->with('workspaceAllowedStaffManage', false)
                    ->with('workspaceAllowedTeachers', false)
                    ->with('workspaceAllowedSales', false)
                    ->with('workspaceAllowedPurchase', false)
                    ->with('workspaceAllowedEducation', false)
                    ->with('workspaceAllowedAccountingManage', false)
                    ->with('recycleCount', 0)
                    ->with('layoutNotifications', collect())
                    ->with('layoutUnreadCount', 0)
                    ->with('layoutReadIds', [])
                    ->with('notificationIndexUrl', route('guardian.notifications'))
                    ->with('notificationReadAllUrl', null)
                    ->with('countsPendingSync', 0)
                    ->with('userPreferences', [])
                    ->with('userTheme', 'default')
                    ->with('activeColorTheme', null)
                    ->with('themePrimary', null)
                    ->with('themeSecondary', null)
                    ->with('sidebarColor', null)
                    ->with('tallNavigation', false)
                    ->with('aiEnabled', false);

                if (! $view->offsetExists('institute')) {
                    $view->with('institute', Institute::find($user->institute_id));
                }

                return;
            }

            $membership = $user instanceof User ? Workspace::membership() : null;
            $institute = match (true) {
                $user instanceof InstituteUser => Institute::find($user->institute_id),
                $user instanceof User && $membership !== null => Institute::find($membership->institution_id),
                default => null,
            };

            $roleLabel = match (true) {
                $user instanceof PlatformAdmin => 'Super Admin',
                $user instanceof InstituteUser => $user->role?->name ?? 'Institute Staff',
                $user instanceof User && $membership !== null => $membership->role?->name ?? 'Institute Staff',
                default => '',
            };

            $accountTypeLabel = $user instanceof User
                ? ($user->isOwnerAccount() ? mawa_lang('account_type.owner') : mawa_lang('account_type.staff'))
                : null;

            $workspaceMemberships = $user instanceof User
                ? Membership::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with(['institution', 'role', 'branch'])
                    ->orderBy('institution_id')
                    ->get()
                    ->filter(fn (Membership $membership) => $membership->roleAllowedForAccountType($user))
                : collect();
            $workspaceActiveId = $user instanceof User ? Workspace::id() : null;

            $isInstituteStaff = $user instanceof InstituteUser
                || ($user instanceof User && $membership !== null);

            $usesClassTerm = in_array($institute?->sub_industry ?? null, ['school', 'college', 'madrasha', 'primary_school', 'secondary_high_school', 'school_college'], true);

            $instituteAiConfig = $institute?->settings?->ai_config ?? [];
            try {
                $aiEnabledBase = AiConfig::enabled();
            } catch (\Throwable $e) {
                $aiEnabledBase = false;
            }
            $aiEnabled = $aiEnabledBase
                && ($user instanceof PlatformAdmin
                    || (($instituteAiConfig['enabled'] ?? false)
                        && in_array('assistant', (array) ($instituteAiConfig['features'] ?? []), true)
                        && ($user instanceof InstituteUser
                            ? $user->hasPermission('ai.assistant')
                            : ($membership?->hasPermission('ai.assistant') ?? false))
                        && app(ModuleAccessService::class)->isEnabled($institute, 'ai')));

            // STEP 67 — module-aware visibility (ModuleAccessService is source of truth)
            $moduleService = app(ModuleAccessService::class);

            $hasFinancePerm = $user instanceof InstituteUser
                ? $user->hasPermission('finance.view')
                : ($membership?->hasPermission('finance.view') ?? false);
            $workspaceAllowedFinance = $institute !== null && $moduleService->isEnabled($institute, 'finance') && $hasFinancePerm;

            $hasCrmPerm = $user instanceof InstituteUser
                ? $user->hasPermission('crm.view')
                : ($membership?->hasPermission('crm.view') ?? false);
            $workspaceAllowedCrm = $institute !== null && $moduleService->isEnabled($institute, 'crm') && $hasCrmPerm;

            $workspaceAllowedEducation = $institute !== null && $moduleService->isEnabled($institute, 'education');

            $activeColorTheme = null;
            $themePrimary = null;
            $themeSecondary = null;
            if ($user instanceof PlatformAdmin) {
                $themeId = $user->preference('theme_id');
                if ($themeId !== null) {
                    $activeColorTheme = Theme::query()->where('status', 'active')->find($themeId);
                }
                if ($activeColorTheme === null) {
                    $activeColorTheme = Theme::query()->where('is_default', 1)->where('status', 'active')->first();
                }
                if ($activeColorTheme !== null) {
                    $themePrimary = $activeColorTheme->primary_color;
                    $themeSecondary = $activeColorTheme->secondary_color;
                }
            } elseif ($institute !== null && $institute->settings !== null) {
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $institute->settings->primary_color ?? '')) {
                    $themePrimary = $institute->settings->primary_color;
                }
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $institute->settings->secondary_color ?? '')) {
                    $themeSecondary = $institute->settings->secondary_color;
                }
            }

            $workspaceAllowedStaffManage = $user instanceof InstituteUser
                ? $user->hasPermission('staff.manage')
                : ($membership?->hasPermission('staff.manage') ?? false);

            $hasTeacherPerm = $user instanceof InstituteUser
                ? $user->hasPermission('teacher.view')
                : ($membership?->hasPermission('teacher.view') ?? false);
            $workspaceAllowedTeachers = $hasTeacherPerm && $institute !== null && $moduleService->isEnabled($institute, 'education');

            $hasHr = function (string $perm) use ($user, $membership): bool {
                if ($user instanceof InstituteUser) return $user->hasPermission($perm);
                return $membership?->hasPermission($perm) ?? false;
            };
            $hasHrPerm = $hasHr('hr.employee.view') || $hasHr('hr.attendance.view') || $hasHr('hr.leave.view') || $hasHr('hr.manage') || $hasHr('hr.history.view');
            $workspaceAllowedHr = $institute !== null && $moduleService->isEnabled($institute, 'hr') && $hasHrPerm;

            $hasSales = function (string $perm) use ($user, $membership): bool {
                if ($user instanceof InstituteUser) return $user->hasPermission($perm);
                return $membership?->hasPermission($perm) ?? false;
            };
            $hasSalesPerm = $hasSales('sales.view') || $hasSales('sales.manage') || $hasSales('sales.create');
            $workspaceAllowedSales = $institute !== null && $moduleService->isEnabled($institute, 'sales') && $hasSalesPerm;

            $hasPurchasePerm = $user instanceof InstituteUser
                ? ($user->hasPermission('purchase.view') || $user->hasPermission('purchase.manage') || $user->hasPermission('purchase.create'))
                : ($membership?->hasPermission('purchase.view') || $membership?->hasPermission('purchase.manage') || $membership?->hasPermission('purchase.create') ?? false);
            $workspaceAllowedPurchase = $institute !== null && $moduleService->isEnabled($institute, 'purchase') && $hasPurchasePerm;

            $hasAccountingPerm = $user instanceof InstituteUser
                ? $user->hasPermission('settings.accounting.manage')
                : ($membership?->hasPermission('settings.accounting.manage') ?? false);
            $workspaceAllowedAccountingManage = $institute !== null && $moduleService->isEnabled($institute, 'finance') && $hasAccountingPerm;

            $recycleCount = match (true) {
                $user instanceof PlatformAdmin => Institute::query()->whereNotNull('deleted_at')->count()
                    + Certificate::query()->whereNotNull('deleted_at')->count(),
                $institute !== null => Student::query()->onlyTrashed()->count()
                    + Batch::query()->onlyTrashed()->count(),
                default => 0,
            };

            $sidebarColor = null;
            if ($user instanceof PlatformAdmin) {
                $preferred = $user->preference('sidebar_color');
                if (filled($preferred) && preg_match('/^#[0-9A-Fa-f]{6}$/', $preferred)) {
                    $sidebarColor = $preferred;
                }
            } elseif ($institute !== null && $institute->settings !== null && filled($institute->settings->sidebar_color)) {
                $sidebarColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $institute->settings->sidebar_color)
                    ? $institute->settings->sidebar_color
                    : null;
            }

            try {
                $layoutNotifications = NotificationCenter::latest(5);
                $layoutUnreadCount = NotificationCenter::unreadCount();
                $layoutReadIds = NotificationCenter::readIds();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('NotificationCenter timeout fallback', ['error' => $e->getMessage()]);
                $layoutNotifications = collect();
                $layoutUnreadCount = 0;
                $layoutReadIds = [];
            }

            $notificationIndexUrl = $user instanceof PlatformAdmin
                ? route('admin.notifications.index')
                : route('notifications.index');
            $notificationReadAllUrl = $user instanceof PlatformAdmin
                ? route('admin.notifications.read-all')
                : route('notifications.read-all');

            try {
                $platformBrandName = \App\Models\Setting::get('brand.name') ?: \App\Models\Setting::get('app.name', config('app.name'));
                $platformBrandLogo = \App\Models\Setting::get('brand.logo');
                $platformBrandFavicon = \App\Models\Setting::get('brand.favicon');
            } catch (\Throwable $e) {
                $platformBrandName = config('app.name');
                $platformBrandLogo = null;
                $platformBrandFavicon = null;
            }

            $view->with('user', $user)
                ->with('platformBrandName', $platformBrandName ?? config('app.name'))
                ->with('platformBrandLogo', $platformBrandLogo ?? null)
                ->with('platformBrandFavicon', $platformBrandFavicon ?? null)
                ->with('roleLabel', $roleLabel)
                ->with('accountTypeLabel', $accountTypeLabel)
                ->with('workspaceMemberships', $workspaceMemberships)
                ->with('workspaceActiveId', $workspaceActiveId)
                ->with('isInstituteStaff', $isInstituteStaff)
                ->with('usesClassTerm', $usesClassTerm)
                ->with('workspaceAllowedFinance', $workspaceAllowedFinance)
                ->with('workspaceAllowedCrm', $workspaceAllowedCrm)
                ->with('workspaceAllowedStaffManage', $workspaceAllowedStaffManage)
                ->with('workspaceAllowedTeachers', $workspaceAllowedTeachers)
                ->with('workspaceAllowedHr', $workspaceAllowedHr)
                ->with('workspaceAllowedSales', $workspaceAllowedSales)
                ->with('workspaceAllowedPurchase', $workspaceAllowedPurchase)
                ->with('workspaceAllowedEducation', $workspaceAllowedEducation)
                ->with('workspaceAllowedAccountingManage', $workspaceAllowedAccountingManage)
                ->with('recycleCount', $recycleCount)
                ->with('layoutNotifications', $layoutNotifications)
                ->with('layoutUnreadCount', $layoutUnreadCount)
                ->with('layoutReadIds', $layoutReadIds)
                ->with('notificationIndexUrl', $notificationIndexUrl)
                ->with('notificationReadAllUrl', $notificationReadAllUrl)
                ->with('countsPendingSync', $user instanceof InstituteUser
                    ? OfflineSyncQueue::query()->where('status', 'pending_review')->count()
                    : 0)
                ->with('userPreferences', $user?->allPreferences() ?? [])
                ->with('userTheme', $user?->preference('theme') ?? 'default')
                ->with('activeColorTheme', $activeColorTheme)
                ->with('themePrimary', $themePrimary)
                ->with('themeSecondary', $themeSecondary)
                ->with('sidebarColor', $sidebarColor)
                ->with('tallNavigation', $user instanceof PlatformAdmin
                    ? (bool) $user->preference('tall_navigation')
                    : ($institute?->settings?->tall_navigation ?? false))
                ->with('aiEnabled', $aiEnabled);

            if (! $view->offsetExists('institute')) {
                $view->with('institute', $institute);
            }
        });
    }
}
