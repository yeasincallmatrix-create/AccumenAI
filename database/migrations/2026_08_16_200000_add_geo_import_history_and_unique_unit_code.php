<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the geo-import history table used by the reusable country-by-country
 * importer, and hardens the geo unit natural key so (country_id, code) can
 * never contain duplicates regardless of which import path wrote them.
 *
 * Existing rows are all unique per (country_id, code) today (verified before
 * this migration), so adding the constraint is safe and non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('geo_imports')) {
            Schema::create('geo_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->string('filename', 255);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('format', 10)->default('jsonl'); // jsonl | json | csv
                $table->string('status', 20)->default('pending'); // pending|validating|importing|completed|failed
                $table->string('mode', 10)->default('upsert');   // upsert|add|validate
                $table->unsignedInteger('total_records')->default(0);
                $table->unsignedInteger('inserted_records')->default(0);
                $table->unsignedInteger('updated_records')->default(0);
                $table->unsignedInteger('skipped_records')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->text('error_summary')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'country_id']);
            });
        }

        if (! Schema::hasTable('administrative_units')) {
            return;
        }

        // Guard the natural key across the whole app (seeders, GeoNames dumps
        // and package imports). MySQL allows multiple NULL code rows, so the
        // constraint has no effect on any NULL-code rows that may exist.
        Schema::table('administrative_units', function (Blueprint $table) {
            $table->unique(['country_id', 'code'], 'au_country_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('administrative_units', function (Blueprint $table) {
            $table->dropUnique('au_country_code_unique');
        });

        Schema::dropIfExists('geo_imports');
    }
};
