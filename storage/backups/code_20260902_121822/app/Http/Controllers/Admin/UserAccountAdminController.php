<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Support\PasswordHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform Admin exclusive — full account lifecycle.
 *
 * EXCEPTION to E24: business deletion NEVER deletes User account.
 * ONLY this controller (platform_admin guard) may delete entire
 * User account with full cleanup (memberships, sessions, tokens, OTPs).
 */
class UserAccountAdminController extends Controller
{
    public const PER_PAGE_OPTIONS = [25, 50, 75, 100, 200];

    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        // Summary cards — actual DB counts
        $summary = [
            'total' => User::withTrashed()->count(),
            'active' => User::where('status', 'active')->count(),
            'banned' => User::where('status', 'inactive')->count(),
            'deleted' => User::onlyTrashed()->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
        ];

        $isDeletedView = $request->query('status') === 'deleted';

        $query = User::query()
            ->withCount(['memberships' => $isDeletedView ? fn($q) => $q->withTrashed() : fn($q) => $q])
            ->when($request->query('q'), function ($q, $term) {
                $term = trim((string) $term);
                if ($term === '') return;
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('id', $term);
                    if (is_string($term) && $term !== '') {
                        $qq->orWhereHas('memberships.institution', fn($iq) => $iq->where('name', 'like', "%{$term}%"));
                    }
                });
            })
            ->when($isDeletedView, fn ($q) => $q->onlyTrashed())
            ->when($request->query('status') === 'active', fn ($q) => $q->where('status', 'active'))
            ->when(in_array($request->query('status') ?? '', ['banned','suspended','inactive'], true), fn ($q) => $q->where('status', 'inactive'))
            ->when($request->query('verification'), function ($q, $v) {
                if ($v === 'verified') $q->whereNotNull('email_verified_at');
                elseif ($v === 'unverified') $q->whereNull('email_verified_at');
            })
            ->when($request->query('business'), function ($q, $b) {
                if ($b === 'has_business') $q->whereHas('memberships');
                elseif ($b === 'no_business') $q->whereDoesntHave('memberships');
                elseif ($b === 'multiple') $q->has('memberships', '>', 1);
            })
            ->when($request->query('account_type'), fn ($q, $type) => $q->where('account_type', $type));

        // Sorting
        $sort = $request->query('sort', 'latest');
        if ($sort === 'oldest') $query->orderBy('id');
        elseif ($sort === 'name') $query->orderBy('name');
        else $query->orderByDesc('id');

        $items = (clone $query)->paginate($perPage)->withQueryString();

        // E26/E27: enrich with business counts for safety UX (active/deleted/total, ownership)
        $enrich = function ($collection) {
            foreach ($collection as $u) {
                try {
                    $active = \App\Models\Membership::where('user_id', $u->id)->whereHas('institution', fn($q)=>$q->whereNull('deleted_at'))->count();
                    $deleted = \App\Models\Membership::onlyTrashed()->where('user_id', $u->id)->count();
                    $deletedByInstitute = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->whereHas('institution', fn($q)=>$q->onlyTrashed())->count();
                    $total = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->count();
                    $ownedActive = \App\Models\Membership::where('user_id', $u->id)->whereHas('role', fn($q)=>$q->where('slug','institute-owner'))->whereHas('institution', fn($q)=>$q->whereNull('deleted_at'))->count();
                    $u->setAttribute('_e26_active_businesses', $active);
                    $u->setAttribute('_e26_deleted_businesses', max($deleted, $deletedByInstitute));
                    $u->setAttribute('_e26_total_memberships', $total);
                    $u->setAttribute('_e26_owned_active', $ownedActive);
                    $roles = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->with('role')->get()->pluck('role.slug')->unique()->filter()->values();
                    $u->setAttribute('_e26_roles', $roles);
                    $u->setAttribute('_last_login', $u->last_login_at);
                } catch (\Throwable $e) {
                    $u->setAttribute('_e26_active_businesses', 0);
                    $u->setAttribute('_e26_deleted_businesses', 0);
                    $u->setAttribute('_e26_total_memberships', $u->memberships_count ?? 0);
                    $u->setAttribute('_e26_owned_active', 0);
                    $u->setAttribute('_e26_roles', collect());
                    $u->setAttribute('_last_login', null);
                }
            }
        };
        $enrich($items);

        return view('admin.users.index', [
            'items' => $items,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'summary' => $summary,
            'filters' => [
                'q' => $request->query('q'),
                'account_type' => $request->query('account_type'),
                'status' => $request->query('status'),
                'verification' => $request->query('verification'),
                'business' => $request->query('business'),
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function bin(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $query = User::onlyTrashed()->withCount(['memberships' => fn($q)=>$q->withTrashed()])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));

        $items = (clone $query)->orderByDesc('deleted_at')->paginate($perPage)->withQueryString();

        // E26: enrich trashed users with business counts (including trashed memberships)
        $enrich = function ($collection) {
            foreach ($collection as $u) {
                try {
                    $active = \App\Models\Membership::where('user_id', $u->id)->whereHas('institution', fn($q)=>$q->whereNull('deleted_at'))->count();
                    $deleted = \App\Models\Membership::onlyTrashed()->where('user_id', $u->id)->count();
                    $withTrashedTotal = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->count();
                    $deletedBusinesses = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->where(function($q){
                        $q->whereNotNull('deleted_at')->orWhereHas('institution', fn($qq)=>$qq->onlyTrashed());
                    })->count();
                    $ownedActive = \App\Models\Membership::where('user_id', $u->id)->whereHas('role', fn($q)=>$q->where('slug','institute-owner'))->whereHas('institution', fn($q)=>$q->whereNull('deleted_at'))->count();
                    $u->setAttribute('_e26_active_businesses', $active);
                    $u->setAttribute('_e26_deleted_businesses', $deletedBusinesses);
                    $u->setAttribute('_e26_total_memberships', $withTrashedTotal);
                    $u->setAttribute('_e26_owned_active', $ownedActive);
                    $roles = \App\Models\Membership::withTrashed()->where('user_id', $u->id)->with('role')->get()->pluck('role.slug')->unique()->filter()->values();
                    $u->setAttribute('_e26_roles', $roles);
                } catch (\Throwable $e) {
                    $u->setAttribute('_e26_active_businesses', 0);
                    $u->setAttribute('_e26_deleted_businesses', 0);
                    $u->setAttribute('_e26_total_memberships', 0);
                    $u->setAttribute('_e26_owned_active', 0);
                    $u->setAttribute('_e26_roles', collect());
                }
            }
        };
        $enrich($items);

        return view('admin.users.bin', [
            'items' => $items,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filters' => [
                'q' => $request->query('q'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['memberships.institution', 'memberships.role']);
        return view('admin.users.show', ['user' => $user]);
    }

    /**
     * Soft delete — platform_admin + password. Full logout, membership soft-delete.
     */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $admin = $request->user();
        $plain = trim((string) $request->input('password'));
        if ($plain === '' || ! PasswordHash::safeCheck($plain, (string) $admin->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Your password is incorrect.', 'errors' => ['password' => ['Your password is incorrect.']]], 422);
            }
            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        if ($user->deleted_at !== null) {
            $msg = 'Account is already in the recycle bin.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['user' => $msg]);
        }

        try {
            AccountDeletionService::softDelete($user, $admin->getKey());
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['user' => $msg]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Account moved to recycle bin. Sessions and tokens revoked.']);
        }
        return redirect()->route('admin.users.index')->with('status', 'Account moved to recycle bin. Sessions and tokens revoked.');
    }

    public function restore(Request $request, User $user): RedirectResponse|JsonResponse
    {
        // Route uses withTrashed, so $user may be trashed — ONLY platform_admin guard reaches here.
        if ($user->deleted_at === null) {
            try { \App\Models\PlatformAuditLog::record('users', 'user.'.$user->id, 'account_restore_rejected', ['user_id'=>$user->id,'reason'=>'not_deleted']); } catch (\Throwable $e) {}
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Account is not in the recycle bin.'], 422);
            }
            return redirect()->route('admin.users.bin')->with('status', 'Account is not in the recycle bin.');
        }

        // Pre-check email/phone collision for deterministic error (service also checks under lock).
        $govCheck = \App\Services\AccountDeletionGovernance::canRestore($user, $request->user(), 'platform_admin');
        if (! $govCheck[0]) {
            try { \App\Models\PlatformAuditLog::record('users', 'user.'.$user->id, 'account_restore_rejected', ['user_id'=>$user->id,'reason'=>$govCheck[2],'message'=>$govCheck[1]]); } catch (\Throwable $e) {}
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $govCheck[1]], 422);
            }
            return back()->withErrors(['user' => $govCheck[1]]);
        }

        try {
            AccountDeletionService::restore($user, $request->user()?->getKey());
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['user' => $msg]);
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Restore failed due to a concurrency conflict. Please retry.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 409);
            }
            return back()->withErrors(['user' => $msg]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Account restored. Stale sessions/tokens revoked — fresh login required.']);
        }
        return redirect()->route('admin.users.bin')->with('status', 'Account restored. Stale sessions/tokens revoked — fresh login required.');
    }

    /**
     * Suspend / Ban — uses existing status field (active → inactive)
     */
    public function suspend(Request $request, User $user): RedirectResponse|JsonResponse
    {
        if ($user->deleted_at !== null) {
            $msg = 'Cannot suspend a deleted account. Restore first.';
            if ($request->expectsJson()) return response()->json(['success'=>false,'message'=>$msg],422);
            return back()->withErrors(['user'=>$msg]);
        }
        if ($user->status === 'inactive') {
            $msg = 'Account is already suspended.';
            if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>$msg]);
            return back()->with('status',$msg);
        }
        $user->update(['status'=>'inactive']);
        try {
            \App\Models\PlatformAuditLog::record('users','user.'.$user->id,'user.suspended',[
                'user_id'=>$user->id,'user_email'=>$user->email,'from_status'=>'active','to_status'=>'inactive',
            ]);
        } catch (\Throwable $e) {}
        if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>'Account suspended. Businesses NOT deleted.']);
        return back()->with('status','Account suspended. Businesses NOT deleted.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse|JsonResponse
    {
        if ($user->deleted_at !== null) {
            $msg = 'Cannot reactivate a deleted account. Restore first.';
            if ($request->expectsJson()) return response()->json(['success'=>false,'message'=>$msg],422);
            return back()->withErrors(['user'=>$msg]);
        }
        if ($user->status === 'active') {
            $msg = 'Account is already active.';
            if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>$msg]);
            return back()->with('status',$msg);
        }
        $user->update(['status'=>'active']);
        try {
            \App\Models\PlatformAuditLog::record('users','user.'.$user->id,'user.reactivated',[
                'user_id'=>$user->id,'user_email'=>$user->email,'from_status'=>'inactive','to_status'=>'active',
            ]);
        } catch (\Throwable $e) {}
        if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>'Account reactivated.']);
        return back()->with('status','Account reactivated.');
    }

    /**
     * Permanent delete — ONLY platform_admin with password.
     * Full cleanup: memberships, sessions, tokens, OTPs, module access, logs.
     * Blocks if user owns active business.
     */
    public function forceDelete(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $admin = $request->user();
        $plain = trim((string) $request->input('password'));
        if ($plain === '' || ! PasswordHash::safeCheck($plain, (string) $admin->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Your password is incorrect.', 'errors' => ['password' => ['Your password is incorrect.']]], 422);
            }
            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        if ($user->deleted_at === null) {
            $msg = 'Account must be in the recycle bin before permanent deletion.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['user' => $msg]);
        }

        [$allowed, $reason] = AccountDeletionService::canForceDelete($user);
        if (! $allowed) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $reason], 422);
            }
            return back()->withErrors(['user' => $reason]);
        }

        AccountDeletionService::forceDelete($user);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Account permanently deleted with full cleanup.']);
        }
        return redirect()->route('admin.users.bin')->with('status', 'Account permanently deleted with full cleanup.');
    }
}
