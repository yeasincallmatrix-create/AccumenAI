<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
            $table->index('deleted_at', 'idx_medicines_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('idx_medicines_deleted_at');
            $table->dropSoftDeletes();
        });
    }
};
