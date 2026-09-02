<?php

namespace App\Http\Controllers;

use App\Http\Controllers\InstituteCreationController;
use App\Models\Membership;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Workspace selection for global accounts holding multiple memberships.
 *
 * A user with exactly one active membership is redirected straight into the
 * portal. Users with several memberships (or none) land on the picker and
 * choose (or create) the organization to work in.
 */
class WorkspaceController extends Controller
{
    public function picker(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $memberships = $this->activeMembershipsFor($user);

        return view('workspace.picker', [
            'memberships' => $memberships,
            'activeId' => Workspace::id(),
        ]);
    }

    public function switch(Request $request, int $institutionId)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! Workspace::verify($institutionId, $user->id)) {
            return back()->withErrors(['workspace' => 'Invalid organization.']);
        }

        Workspace::set($institutionId);

        return redirect()->route('dashboard');
    }

    /**
     * Step 2 of owner onboarding — create a new organization.
     * Delegates to InstituteCreationController so GET /workspace/create
     * (route workspace.create) remains the canonical endpoint.
     * @see InstituteCreationController::create()
     */
    public function create(Request $request): View|RedirectResponse
    {
        return app(InstituteCreationController::class)->create($request);
    }

    /**
     * Persist a new organization for the authenticated owner.
     * @see InstituteCreationController::store()
     */
    public function store(Request $request): RedirectResponse
    {
        return app(InstituteCreationController::class)->store($request);
    }

    /**
     * The global account's active, non-deleted memberships that are
     * consistent with the account type (defense-in-depth).
     */
    protected function activeMembershipsFor(User $user)
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['institution', 'role', 'branch'])
            ->orderBy('institution_id')
            ->get()
            ->filter(fn (Membership $membership) => $membership->roleAllowedForAccountType($user));
    }
}
