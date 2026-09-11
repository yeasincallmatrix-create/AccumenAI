<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 — RxNorm terminology layer (NLM RxNav, monthly releases).
 *
 * - rxnorm_concepts: one row per source RXCUI (stable source identity),
 *   carrying TTY semantics, release provenance and match state. Candidate
 *   product sets for ambiguous rows live here as data, never as merges.
 * - rxnorm_import_batches: auditable runs (mirrors the DGDA batch shape
 *   deliberately — same ops discipline, separate release semantics).
 * - medicine_identifiers.medicine_ingredient_id: ingredient-level RXCUIs
 *   (IN/PIN/MIN) attach to ingredients, product-level ones (SCD/SBD/…) to
 *   products. Exactly one side is ever set (enforced in service code;
 *   cross-version MySQL CHECK support is unreliable on the project's
 *   XAMPP/MariaDB matrix, so the invariant is tested, not constrained).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rxnorm_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('rxnorm_rxnav');
            $table->string('release_version', 100)->nullable();
            $table->string('filename', 200)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status', 20)->default('pending'); // pending|partial|complete|failed
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_valid')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_unchanged')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->unsignedInteger('records_unmapped')->default(0);
            $table->unsignedInteger('records_ambiguous')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rxnorm_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rxnorm_import_batch_id')->nullable()
                ->constrained('rxnorm_import_batches')->nullOnDelete();
            // Stable RxNav identity.
            $table->string('rxcui', 20)->unique();
            $table->string('name', 255);
            // IN|PIN|MIN|BN|SCD|SBD|GPCK|BPCK|DF|… (validated against the
            // known set on import; unknown TTYs are rejected, not stored).
            $table->string('tty', 12);
            $table->string('source_release', 100)->nullable();
            // active|retired|suppressed — retirements never delete rows.
            $table->string('status', 20)->default('active');
            $table->string('replaced_by_rxcui', 20)->nullable();
            // Normalized link targets (nullable until deterministically matched).
            $table->foreignId('medicine_product_id')->nullable()
                ->constrained('medicine_products')->nullOnDelete();
            $table->foreignId('medicine_ingredient_id')->nullable()
                ->constrained('medicine_ingredients')->nullOnDelete();
            $table->string('match_status', 20)->default('unmapped');
            // unmapped|exact|deterministic|relationship|ambiguous|invalid
            $table->text('match_detail')->nullable();
            $table->json('candidates')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamps();

            $table->index('medicine_product_id');
            $table->index('medicine_ingredient_id');
            $table->index('match_status');
            $table->index('tty');
        });

        Schema::table('medicine_identifiers', function (Blueprint $table) {
            // Ingredient-level RXCUIs (IN/PIN/MIN) attach to ingredients, so
            // the product side becomes nullable (existing rows all carry a
            // product — verified zero NULLs before/after in both DBs).
            $table->foreignId('medicine_ingredient_id')->nullable()->after('medicine_product_id');
        });
        DB::statement('ALTER TABLE `medicine_identifiers` MODIFY `medicine_product_id` BIGINT UNSIGNED NULL');
        Schema::table('medicine_identifiers', function (Blueprint $table) {
            $table->foreign('medicine_ingredient_id', 'identifiers_ingredient_fk')
                ->references('id')->on('medicine_ingredients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Only safe while no ingredient-side identifier rows exist (they
        // would violate the restored NOT NULL) — dev/test-only path.
        Schema::table('medicine_identifiers', function (Blueprint $table) {
            $table->dropForeign('identifiers_ingredient_fk');
            $table->dropColumn('medicine_ingredient_id');
        });
        // Down restores NOT NULL only when no ingredient-side rows exist.
        DB::statement('ALTER TABLE `medicine_identifiers` MODIFY `medicine_product_id` BIGINT UNSIGNED NOT NULL');

        Schema::dropIfExists('rxnorm_concepts');
        Schema::dropIfExists('rxnorm_import_batches');
    }
};
