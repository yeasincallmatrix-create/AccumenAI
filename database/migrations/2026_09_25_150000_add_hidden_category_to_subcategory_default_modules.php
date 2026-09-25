<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Universal Module Config matrix can now park a module in a fourth
     * bucket, 'hidden' (never surfaced to matching tenants). The original enum
     * only allowed mandatory/default/optional, so widen it.
     *
     * Additive only: no rows are rewritten and existing values keep working.
     * Guarded so re-running (or running against an already-widened DB) is a no-op.
     */
    public function up(): void
    {
        if (! $this->needsWidening()) {
            return;
        }

        Schema::table('subcategory_default_modules', function (Blueprint $table) {
            $table->enum('category', ['mandatory', 'default', 'optional', 'hidden'])
                ->default('default')
                ->change();
        });
    }

    public function down(): void
    {
        if (! $this->hasHidden()) {
            return;
        }

        // Rows parked in 'hidden' cannot be represented by the narrowed enum.
        DB::statement("UPDATE `subcategory_default_modules` SET `category` = 'optional' WHERE `category` = 'hidden'");

        Schema::table('subcategory_default_modules', function (Blueprint $table) {
            $table->enum('category', ['mandatory', 'default', 'optional'])
                ->default('default')
                ->change();
        });
    }

    private function needsWidening(): bool
    {
        return Schema::hasTable('subcategory_default_modules') && ! $this->hasHidden();
    }

    private function hasHidden(): bool
    {
        if (! Schema::hasTable('subcategory_default_modules')) {
            return false;
        }

        $type = $this->categoryType();

        return $type !== null && str_contains($type, "'hidden'");
    }

    private function categoryType(): ?string
    {
        try {
            return Schema::getColumnType('subcategory_default_modules', 'category', fullDefinition: true);
        } catch (Throwable) {
            return null;
        }
    }
};
