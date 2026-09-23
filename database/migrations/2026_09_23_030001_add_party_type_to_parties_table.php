<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parties')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table) {
            if (!Schema::hasColumn('parties', 'party_type')) {
                $table->string('party_type', 20)->default('customer')->after('name');
            }
            if (!Schema::hasColumn('parties', 'is_customer')) {
                $table->boolean('is_customer')->default(true)->after('party_type');
            }
            if (!Schema::hasColumn('parties', 'is_vendor')) {
                $table->boolean('is_vendor')->default(false)->after('is_customer');
            }
        });

        // Backfill party_type / flags from existing type values (Case A: type is source data).
        DB::table('parties')->where('type', 'customer')->update([
            'party_type' => 'customer',
            'is_customer' => true,
            'is_vendor' => false,
        ]);
        DB::table('parties')->where('type', 'supplier')->update([
            'party_type' => 'vendor',
            'is_customer' => false,
            'is_vendor' => true,
        ]);
        DB::table('parties')->where('type', 'both')->update([
            'party_type' => 'both',
            'is_customer' => true,
            'is_vendor' => true,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('parties')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table) {
            foreach (['party_type', 'is_customer', 'is_vendor'] as $col) {
                if (Schema::hasColumn('parties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
