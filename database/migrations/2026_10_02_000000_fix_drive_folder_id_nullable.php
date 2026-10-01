<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_drive_connections', function (Blueprint $table) {
            // drive_folder_id is populated AFTER the first
            // ensureTenantFolderStructure() call → must be nullable at
            // connection time (callback() insert was rejected by 1364).
            $table->string('drive_folder_id', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_drive_connections', function (Blueprint $table) {
            $table->string('drive_folder_id', 100)->nullable(false)->change();
        });
    }
};
