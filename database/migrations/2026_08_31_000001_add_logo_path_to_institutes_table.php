<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            if (! Schema::hasColumn('institutes', 'logo_path')) {
                // spec says after 'cover' — but actual column is 'cover_photo', fallback to 'logo'
                if (Schema::hasColumn('institutes', 'cover_photo')) {
                    $table->string('logo_path')->nullable()->after('cover_photo');
                } elseif (Schema::hasColumn('institutes', 'logo')) {
                    $table->string('logo_path')->nullable()->after('logo');
                } else {
                    $table->string('logo_path')->nullable();
                }
            }
        });

        // Backfill logo_path from existing logo column for existing institutes
        try {
            if (Schema::hasColumn('institutes', 'logo') && Schema::hasColumn('institutes', 'logo_path')) {
                \Illuminate\Support\Facades\DB::statement("UPDATE institutes SET logo_path = logo WHERE logo IS NOT NULL AND (logo_path IS NULL OR logo_path = '')");
            }
        } catch (\Throwable $e) {
            // non-critical
        }
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            if (Schema::hasColumn('institutes', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
        });
    }
};
