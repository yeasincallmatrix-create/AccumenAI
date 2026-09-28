<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\CreditLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditControlController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $limits = CreditLimit::query()->latest()->paginate(20);

        return view('dealership.credit_control.index', [
            'institute' => $institute,
            'limits' => $limits,
        ]);
    }

    public function block(Request $request, $customer): RedirectResponse
    {
        $this->requireInstitute($request);

        CreditLimit::updateOrCreate(
            ['customer_id' => (int) $customer],
            ['is_blocked' => true, 'last_reviewed_at' => now()]
        );

        return back()->with('success', 'Customer blocked.');
    }

    public function unblock(Request $request, $customer): RedirectResponse
    {
        $this->requireInstitute($request);

        CreditLimit::updateOrCreate(
            ['customer_id' => (int) $customer],
            ['is_blocked' => false, 'last_reviewed_at' => now()]
        );

        return back()->with('success', 'Customer unblocked.');
    }
}
