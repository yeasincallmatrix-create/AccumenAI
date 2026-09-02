<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enables soft-deleting batches so a deleted batch lands in the institute
 * recycle bin (restore / permanent delete) instead of being cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
