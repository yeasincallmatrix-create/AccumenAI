<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\InstitutePaymentGateway;
use App\Models\OnlinePaymentAttempt;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\GatewayCallbackService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceOnlinePaymentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly GatewayCallbackService $callbackService,
    ) {}

    public function gateways(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $allGateways = PaymentGateway::query()->where('is_active', true)->get();
        $enabledGatewayIds = InstitutePaymentGateway::query()
            ->where('institute_id', $institute->id)
            ->pluck('gateway_id')
            ->toArray();

        return view('institute.finance.online-payments.gateways', [
            'institute' => $institute,
            'allGateways' => $allGateways,
            'enabledGatewayIds' => $enabledGatewayIds,
        ]);
    }

    public function enableGateway(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        InstitutePaymentGateway::updateOrCreate(
            ['institute_id' => $institute->id, 'gateway_id' => $gateway->id],
            ['is_enabled' => true, 'credentials' => []],
        );

        return back()->with('status', $gateway->name.' enabled.');
    }

    public function disableGateway(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        InstitutePaymentGateway::where('institute_id', $institute->id)
            ->where('gateway_id', $gateway->id)
            ->update(['is_enabled' => false]);

        return back()->with('status', $gateway->name.' disabled.');
    }

    public function updateCredentials(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'credentials' => ['required', 'array'],
        ]);

        InstitutePaymentGateway::where('institute_id', $institute->id)
            ->where('gateway_id', $gateway->id)
            ->update(['credentials' => $data['credentials']]);

        return back()->with('status', $gateway->name.' credentials updated.');
    }

    public function webhook(Request $request, string $gatewaySlug): \Symfony\Component\HttpFoundation\Response
    {
        $payload = $request->all();
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');

        try {
            $attempt = $this->callbackService->handleCallback($gatewaySlug, $payload, $signature);

            return response()->json([
                'status' => 'ok',
                'attempt_id' => $attempt->id,
                'attempt_status' => $attempt->status,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function attempts(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $attempts = OnlinePaymentAttempt::query()
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($s) => $s->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->with(['gateway', 'invoice', 'student', 'payment'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('institute.finance.online-payments.attempts', [
            'institute' => $institute,
            'attempts' => $attempts,
        ]);
    }
}
