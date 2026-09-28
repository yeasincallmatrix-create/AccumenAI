<?php

namespace App\Http\Controllers\Dealership\Api;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\ApiEndpoint;
use App\Services\Dealership\Api\ApiEndpointRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiEndpointController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $endpoints = ApiEndpoint::query()->orderBy('version')->orderBy('endpoint_key')->paginate(50);

        return view('dealership.api.endpoints.index', [
            'institute' => $institute,
            'endpoints' => $endpoints,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'endpoint_key' => ['required', 'string', 'max:80'],
            'http_method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'uri' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'required_permission' => ['nullable', 'string', 'max:80'],
        ]);

        ApiEndpoint::updateOrCreate(
            [
                'institute_id' => $institute->id,
                'endpoint_key' => $data['endpoint_key'],
                'http_method' => $data['http_method'],
            ],
            [
                'uri' => $data['uri'],
                'description' => $data['description'] ?? null,
                'required_permission' => $data['required_permission'] ?? null,
                'is_enabled' => true,
                'version' => config('dealership.api.api_version', 'v1'),
            ]
        );

        return redirect()->route('dealership.api.endpoints.index')->with('success', 'Endpoint saved.');
    }

    public function toggle(Request $request, ApiEndpoint $endpoint): RedirectResponse
    {
        $this->requireInstitute($request);

        $endpoint->update(['is_enabled' => ! $endpoint->is_enabled]);

        return back()->with('success', 'Endpoint toggled.');
    }
}
