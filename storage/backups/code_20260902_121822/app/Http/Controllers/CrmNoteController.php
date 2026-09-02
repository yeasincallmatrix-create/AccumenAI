<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\CrmNote;
use App\Services\CrmNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRM notes (Step 31). Same security model as CrmContactController.
 */
class CrmNoteController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmNoteService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['contact', 'organization', 'lead'])],
            'subject_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->service->create(
            $data,
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Note saved.');
    }

    public function destroy(Request $request, CrmNote $note): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($note, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Note removed.');
    }
}
