<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 37 — extend the audit action enum with 'waive' so approved education
 * fee waivers can be recorded on the same append-only audit trail as every
 * other financial write. Additive only; existing values are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_audit_trails')) {
            Schema::table('accounting_audit_trails', function (Blueprint $table) {
                $table->enum('action', [
                    'create',
                    'update',
                    'delete',
                    'post',
                    'reverse',
                    'void',
                    'waive',
                    'lock',
                    'close',
                    'reopen',
                    'import',
                    'migrate',
                    'export',
                ])->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_audit_trails')) {
            Schema::table('accounting_audit_trails', function (Blueprint $table) {
                $table->enum('action', [
                    'create',
                    'update',
                    'delete',
                    'post',
                    'reverse',
                    'void',
                    'lock',
                    'close',
                    'reopen',
                    'import',
                    'migrate',
                    'export',
                ])->change();
            });
        }
    }
};
