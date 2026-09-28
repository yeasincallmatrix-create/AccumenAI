<?php

namespace App\Http\Controllers\Dealership\Api;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Dealership\Api\ApiDocGenerator;
use App\Services\Dealership\Api\ApiEndpointRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiDocController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ApiDocGenerator $docs,
        private readonly ApiEndpointRegistrar $registrar,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('dealership.api.docs.index', [
            'institute' => $institute,
            'docs' => $this->docs->generate($institute->id),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->registrar->seedDefaults($institute->id);

        return redirect()->route('dealership.api.docs.index')->with('success', 'API docs regenerated.');
    }
}
