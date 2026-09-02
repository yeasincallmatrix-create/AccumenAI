<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migrates legacy per-institute staff accounts (institute_users) into the
 * workspace architecture:
 *
 *   institute_users  ->  users (global account) + institution_user (membership)
 *
 * Global identity + auth columns land on `users`; institution-scoped
 * attributes (role, branch, staff fields) land on `institution_user`.
 *
 * Safe to run repeatedly: membership rows are matched by their
 * legacy_institute_user_id, and user rows by their unique email/phone.
 * Reversible via --rollback.
 */
class MigrateMemberships extends Command
{
    protected $signature = 'membership:migrate
                            {--pretend : Print the planned changes without applying them.}
                            {--rollback : Reverse the migration (remove created memberships + orphaned users).}';

    protected $description = 'Convert institute_users into users + institution_user memberships (Phase 2).';

    public function handle(): int
    {
        if (app()->environment('production') && $this->option('rollback')) {
            $this->error('membership:migrate --rollback is blocked in production (would delete users/memberships). Run in local/testing or backup first.');
            return self::FAILURE;
        }
        if (app()->environment('production') && ! config('app.demo_seed_enabled', env('DEMO_SEED_ENABLED', false)) && ! $this->option('pretend')) {
            $this->warn('Running membership:migrate in production — ensure backup exists. Use --pretend first.');
            if (! $this->confirm('Continue migration in production?')) {
                return self::FAILURE;
            }
        }
        return $this->option('rollback')
            ? $this->rollback()
            : $this->migrate();
    }

    protected function migrate(): int
    {
        $pretend = $this->option('pretend');

        $staff = DB::table('institute_users')
            ->orderBy('id')
            ->get();

        $validInstitutes = DB::table('institutes')->pluck('id')->all();
        $validRoles = DB::table('roles')->pluck('id')->all();
        $validBranches = DB::table('branches')->pluck('id')->all();

        $createdUsers = 0;
        $createdMemberships = 0;
        $matchedExisting = 0;
        $skippedOrphans = 0;

        DB::transaction(function () use (
            $pretend, $staff, $validInstitutes, $validRoles, $validBranches,
            &$createdUsers, &$createdMemberships, &$matchedExisting, &$skippedOrphans
        ) {
            foreach ($staff as $row) {
                // Skip rows already migrated (matched by legacy id).
                if (DB::table('institution_user')
                    ->where('legacy_institute_user_id', $row->id)
                    ->exists()) {
                    continue;
                }

                // Skip rows referencing deleted/nonexistent institutes, roles
                // or branches (FK integrity) — report them for manual review.
                if (! in_array($row->institute_id, $validInstitutes, true)
                    || ! in_array($row->role_id, $validRoles, true)
                    || ($row->branch_id !== null && ! in_array($row->branch_id, $validBranches, true))) {
                    $skippedOrphans++;
                    $this->warn("  [skip] institute_user #{$row->id} -> invalid institute/role/branch reference.");

                    continue;
                }

                $user = $pretend
                    ? null
                    : DB::table('users')->where('email', $row->email)->first();

                if ($user === null) {
                    if (! $pretend) {
                        $userId = $this->createUser($row);
                    }
                    $createdUsers++;
                } else {
                    $userId = $user->id;
                    $matchedExisting++;
                }

                if (! $pretend) {
                    $this->createMembership($row, $userId);
                }
                $createdMemberships++;
            }
        });

        $verb = $pretend ? 'Would create' : 'Created';
        $this->info("{$verb} {$createdUsers} user account(s), {$createdMemberships} membership(s); matched {$matchedExisting} existing user(s); skipped {$skippedOrphans} orphaned row(s).");

        return self::SUCCESS;
    }

    protected function createUser(object $row): int
    {
        $status = $row->status === 'active' ? 'active' : 'inactive';
        $roleSlug = DB::table('roles')->where('id', $row->role_id)->value('slug');

        return DB::table('users')->insertGetId([
            'uuid' => $row->uuid ?: (string) Str::uuid(),
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email,
            'phone' => $row->phone,
            'preferred_language' => $row->preferred_language ?? 'en',
            'photo' => $row->photo,
            'email_verified_at' => $row->email_verified_at,
            'two_factor_secret' => $row->two_factor_secret,
            'two_factor_recovery_codes' => $row->two_factor_recovery_codes,
            'two_factor_confirmed_at' => $row->two_factor_confirmed_at,
            'remember_token' => $row->remember_token,
            'failed_login_count' => $row->failed_login_count ?? 0,
            'locked_until' => $row->locked_until,
            'last_login_at' => $row->last_login_at,
            'last_login_ip' => $row->last_login_ip,
            'password_hash' => $row->password_hash,
            'status' => $status,
            'account_type' => $roleSlug === 'institute-owner' ? 'owner' : 'staff',
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    protected function createMembership(object $row, int $userId): void
    {
        DB::table('institution_user')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'institution_id' => $row->institute_id,
            'role_id' => $row->role_id,
            'branch_id' => $row->branch_id,
            'employee_id' => $row->employee_id,
            'designation' => $row->designation,
            'department' => $row->department,
            'qualification' => $row->qualification,
            'salary' => $row->salary,
            'joining_date' => $row->joining_date,
            'father_name' => $row->father_name,
            'mother_name' => $row->mother_name,
            'religion' => $row->religion,
            'gender' => $row->gender,
            'photo' => $row->photo,
            'nid_photo' => $row->nid_photo,
            'status' => $row->status,
            'legacy_institute_user_id' => $row->id,
            'deleted_at' => $row->deleted_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    protected function rollback(): int
    {
        $memberships = DB::table('institution_user')
            ->whereNotNull('legacy_institute_user_id')
            ->get(['id', 'user_id']);

        $userIds = $memberships->pluck('user_id')->unique();

        $membershipCount = $memberships->count();

        DB::transaction(function () use ($memberships, $userIds) {
            $ids = $memberships->pluck('id')->all();
            if ($ids !== []) {
                DB::table('institution_user')->whereIn('id', $ids)->delete();
            }

            foreach ($userIds as $userId) {
                $remaining = DB::table('institution_user')
                    ->where('user_id', $userId)
                    ->exists();

                if (! $remaining) {
                    DB::table('users')->where('id', $userId)->delete();
                }
            }
        });

        $this->info("Removed {$membershipCount} membership(s) and orphaned user account(s).");

        return self::SUCCESS;
    }
}
