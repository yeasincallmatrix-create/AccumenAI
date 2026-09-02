<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\OnlinePaymentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\ModuleRegistry;
use Illuminate\View\View;

class SaasAdminController extends Controller
{
    public function subscriptionDashboard(): View
    {
        $totalInstitutes = Institute::count();
        $activeCount = Institute::whereHas('instituteSubscriptions', function ($q) {
            $q->where('end_date', '>=', now());
        })->count();
        $expiredCount = $totalInstitutes - $activeCount;

        $totalRevenue = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_COMPLETED)
            ->sum('amount');

        $recentSubscriptions = InstituteSubscription::with(['institute', 'package'])
            ->latest('id')
            ->limit(10)
            ->get();

        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get();

        return view('saas.admin.dashboard', compact(
            'totalInstitutes',
            'activeCount',
            'expiredCount',
            'totalRevenue',
            'recentSubscriptions',
            'packages',
        ));
    }

    public function usageReport(): View
    {
        $institutes = Institute::with('package')
            ->withCount('instituteSubscriptions')
            ->orderBy('name')
            ->get();

        $modules = ModuleRegistry::orderBy('sort_order')->get();

        $usageData = [];
        foreach ($institutes as $inst) {
            $enabledModules = $inst->enabledModules();
            $usageData[$inst->id] = [
                'institute' => $inst,
                'enabled_modules' => $enabledModules,
                'module_count' => count($enabledModules),
            ];
        }

        return view('saas.admin.usage', compact('institutes', 'modules', 'usageData'));
    }

    public function billingReport(): View
    {
        $periods = [
            'today' => now()->startOfDay(),
            'this_week' => now()->startOfWeek(),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
        ];

        $revenueByPeriod = [];
        foreach ($periods as $label => $startDate) {
            $revenueByPeriod[$label] = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_COMPLETED)
                ->where('created_at', '>=', $startDate)
                ->sum('amount');
        }

        $totalAttempts = OnlinePaymentAttempt::count();
        $successfulAttempts = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_COMPLETED)->count();
        $failedAttempts = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_FAILED)->count();
        $pendingAttempts = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_PENDING)->count();
        $successRate = $totalAttempts > 0 ? round(($successfulAttempts / $totalAttempts) * 100, 1) : 0;

        $recentPayments = OnlinePaymentAttempt::with('institute')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('saas.admin.billing', compact(
            'revenueByPeriod',
            'totalAttempts',
            'successfulAttempts',
            'failedAttempts',
            'pendingAttempts',
            'successRate',
            'recentPayments',
        ));
    }

    public function featureLimits(): View
    {
        $packages = SubscriptionPackage::where('status', 'active')
            ->with(['packageModules.module'])
            ->orderBy('id')
            ->get();

        $modules = ModuleRegistry::orderBy('sort_order')->get();

        $institutesWithPackages = Institute::with('package')
            ->orderBy('name')
            ->get();

        return view('saas.admin.limits', compact('packages', 'modules', 'institutesWithPackages'));
    }
}
