<?php

namespace App\Http\Controllers;

use App\Models\CertificateType;
use App\Services\CertificateTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateTypeController extends Controller
{
    public function __construct(
        private readonly CertificateTypeService $service,
    ) {}

    public function index(Request $request): View
    {
        $types = CertificateType::query()
            ->where('institute_id', $request->user()->institute_id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('certificate-types.index', ['types' => $types]);
    }

    public function create(): View
    {
        return view('certificate-types.form', [
            'type' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['is_active'] = $data['is_active'] ?? true;
        $data['display_order'] = $data['display_order'] ?? 0;

        $this->service->create((int) $request->user()->institute_id, $data);

        return redirect()->route('certificate-types.index')
            ->with('status', 'Certificate type created.');
    }

    public function edit(CertificateType $certificateType): View
    {
        abort_unless($certificateType->institute_id === auth()->user()->institute_id, 403);

        return view('certificate-types.form', [
            'type' => $certificateType,
        ]);
    }

    public function update(Request $request, CertificateType $certificateType): RedirectResponse
    {
        abort_unless($certificateType->institute_id === $request->user()->institute_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['is_active'] = $data['is_active'] ?? $certificateType->is_active;
        $data['display_order'] = $data['display_order'] ?? $certificateType->display_order;

        $this->service->update($certificateType, $data);

        return redirect()->route('certificate-types.index')
            ->with('status', 'Certificate type updated.');
    }

    public function destroy(CertificateType $certificateType): RedirectResponse
    {
        abort_unless($certificateType->institute_id === auth()->user()->institute_id, 403);

        $this->service->destroy($certificateType);

        return redirect()->route('certificate-types.index')
            ->with('status', 'Certificate type deleted.');
    }
}
