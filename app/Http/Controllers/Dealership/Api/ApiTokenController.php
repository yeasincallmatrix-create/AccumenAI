<?php

namespace App\Http\Controllers\Dealership\Api;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\ApiToken;
use App\Models\Dealership\SalesForce;
use App\Services\Dealership\Api\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ApiTokenService $service,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $tokens = ApiToken::query()->latest()->paginate(20);

        return view('dealership.api.tokens.index', [
            'institute' => $institute,
            'tokens' => $tokens,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1', 'exists:dealership_sales_force,id'],
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:80'],
        ]);

        $sr = SalesForce::withoutGlobalScope('institute')->findOrFail($data['sales_force_id']);

        ['plaintext' => $raw, 'token' => $token] = $this->service->issueToken(
            $sr,
            $data['name'],
            $data['abilities'] ?? []
        );

        return redirect()->route('dealership.api.tokens.index')->with([
            'success' => 'Token issued for '.$sr->name.'.',
            'api_plaintext' => $raw,
            'api_token_id' => $token->id,
        ]);
    }

    public function revoke(Request $request, ApiToken $token): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->revokeToken($token);

        return back()->with('success', 'Token revoked.');
    }
}
