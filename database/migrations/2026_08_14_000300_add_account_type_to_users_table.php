<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- SAFETY CHECKS (read-only; abort before any ALTER) ----

        $mixed = DB::table('users as u')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('institution_user as m')
                ->join('roles as r', 'r.id', '=', 'm.role_id')
                ->whereColumn('m.user_id', 'u.id')->where('r.slug', 'institute-owner'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('institution_user as m2')
                ->join('roles as r2', 'r2.id', '=', 'm2.role_id')
                ->whereColumn('m2.user_id', 'u.id')->where('r2.slug', '!=', 'institute-owner'))
            ->get(['u.id', 'u.name', 'u.email']);

        $noMembership = DB::table('users as u')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('institution_user as m')
                ->whereColumn('m.user_id', 'u.id'))
            ->get(['u.id', 'u.name', 'u.email']);

        if ($mixed->isNotEmpty() || $noMembership->isNotEmpty()) {
            $lines = [];
            if ($mixed->isNotEmpty()) {
                $lines[] = 'MIXED owner+staff accounts: '.$mixed->map(fn ($u) => "#{$u->id} {$u->email}")->implode(', ');
            }
            if ($noMembership->isNotEmpty()) {
                $lines[] = 'NO-MEMBERSHIP accounts: '.$noMembership->map(fn ($u) => "#{$u->id} {$u->email}")->implode(', ');
            }
            throw new RuntimeException('Cannot backfill account_type — '.implode('; ', $lines).'. Classify manually first.');
        }

        // ---- ADD COLUMN (default 'owner' for NEW registrations; NOT used for backfill) ----
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['owner', 'staff'])
                ->default('owner')
                ->after('status');
        });

        // ---- BACKFILL STRICTLY FROM MEMBERSHIPS ----
        // owner = has institute-owner membership (guarded: no staff membership exists)
        DB::table('users as u')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('institution_user as m')
                ->join('roles as r', 'r.id', '=', 'm.role_id')
                ->whereColumn('m.user_id', 'u.id')->where('r.slug', 'institute-owner'))
            ->update(['account_type' => 'owner']);

        // staff = has staff membership (guarded: no owner membership exists)
        DB::table('users as u')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('institution_user as m')
                ->join('roles as r', 'r.id', '=', 'm.role_id')
                ->whereColumn('m.user_id', 'u.id')->where('r.slug', '!=', 'institute-owner'))
            ->update(['account_type' => 'staff']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
