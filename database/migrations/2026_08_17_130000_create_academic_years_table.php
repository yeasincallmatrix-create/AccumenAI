<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic sessions/years owned by each institute (e.g. "2026", "Session 2026-27").
 *
 * Institute-scoped on purpose: the school calendar is an operational concept
 * (each institute defines its own sessions), unlike the country-scoped global
 * academic structure masters. A student academic placement is always tied to
 * one of these years so 2026 and 2027 placements coexist historically.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_years')) {
            Schema::create('academic_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);                 // display label, e.g. "Academic Year 2026"
                $table->string('code', 40);                  // "2026" / "2026-27" — unique per institute
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_current')->default(false);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['institute_id', 'code']);
                $table->index(['institute_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
