<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — Medicine architecture & terminology foundation.
 *
 * Separates GLOBAL terminology (concepts, ingredients, forms, routes,
 * products, external identifiers) from TENANT catalog data
 * (institute_medicines) and INVENTORY (pharmacy_stock, untouched).
 *
 * Delete semantics (Phase 03 spirit — no destructive cascades into
 * clinical/financial history):
 * - terminology-internal links use RESTRICT, except product→ingredients
 *   pivots which are pure dependents (SAFE CASCADE);
 * - institute_medicines rows die with their tenant (CASCADE, like every
 *   other tenant row) but products are RESTRICT-protected;
 * - prescription snapshot columns carry NO foreign keys by design —
 *   snapshots must stay immutable even if terminology is later remapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name', 190);
            $table->string('normalized_name', 190)->unique();
            $table->text('description')->nullable();
            $table->string('concept_type', 20)->default('single'); // single|combination
            $table->string('status', 20)->default('active'); // active|deprecated|retired
            $table->timestamps();
        });

        Schema::create('medicine_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name', 190);
            $table->string('normalized_name', 190)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_concept_id')->constrained('medicine_concepts')->restrictOnDelete();
            $table->foreignId('medicine_form_id')->nullable()->constrained('medicine_forms')->nullOnDelete();
            $table->foreignId('medicine_route_id')->nullable()->constrained('medicine_routes')->nullOnDelete();
            $table->string('display_name', 200);
            $table->string('normalized_name', 220)->unique();
            $table->string('strength_raw', 100)->nullable();
            $table->string('brand_name', 150)->nullable();
            $table->string('category', 100)->nullable();
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('storage_conditions')->nullable();
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('medicine_concept_id');
        });

        Schema::create('medicine_product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_product_id')->constrained('medicine_products')->cascadeOnDelete();
            $table->foreignId('medicine_ingredient_id')->constrained('medicine_ingredients')->restrictOnDelete();
            $table->decimal('strength_value', 12, 3)->nullable();
            $table->string('strength_unit', 20)->nullable();
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['medicine_product_id', 'medicine_ingredient_id', 'sequence'], 'uq_product_ingredient_seq');
        });

        Schema::create('medicine_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_product_id')->constrained('medicine_products')->restrictOnDelete();
            $table->string('system', 40); // dgda|rxnorm|atc|ndc|gtin|…
            $table->string('identifier_type', 40); // code|dar_number|concept_id|rxcui|…
            $table->string('value', 190);
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['system', 'identifier_type', 'value'], 'uq_identifier_system_value');
            $table->index('medicine_product_id');
        });

        Schema::create('institute_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('medicine_product_id')->constrained('medicine_products')->restrictOnDelete();
            $table->string('local_code', 50);
            $table->string('local_name', 200)->nullable();
            $table->boolean('preferred')->default(false);
            $table->boolean('active')->default(true);
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('vat_percentage', 5, 2)->nullable();
            $table->integer('reorder_level')->nullable();
            $table->integer('reorder_quantity')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'local_code'], 'uq_institute_local_code');
            $table->index(['institute_id', 'active']);
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            // Immutable snapshots (no FKs by design — see class docblock).
            $table->unsignedBigInteger('medicine_concept_id')->nullable()->after('medicine_id');
            $table->unsignedBigInteger('medicine_product_id')->nullable()->after('medicine_concept_id');
            $table->string('display_name_snapshot', 200)->nullable()->after('medicine_name');
            $table->string('strength_snapshot', 100)->nullable()->after('display_name_snapshot');
            $table->string('dosage_form_snapshot', 50)->nullable()->after('strength_snapshot');
            $table->string('route_snapshot', 50)->nullable()->after('dosage_form_snapshot');
            $table->string('rxnorm_code_snapshot', 20)->nullable()->after('route_snapshot');
            $table->index('medicine_product_id', 'rx_items_product_snapshot_index');
        });

        Schema::table('medicines', function (Blueprint $table) {
            // Compatibility link to the normalized product (nullable until
            // mapped; nullOnDelete so terminology work never breaks catalog).
            $table->foreignId('medicine_product_id')->nullable()->after('institute_id')
                ->constrained('medicine_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropForeign(['medicine_product_id']);
            $table->dropColumn('medicine_product_id');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropIndex('rx_items_product_snapshot_index');
            $table->dropColumn([
                'medicine_concept_id', 'medicine_product_id', 'display_name_snapshot',
                'strength_snapshot', 'dosage_form_snapshot', 'route_snapshot',
                'rxnorm_code_snapshot',
            ]);
        });

        Schema::dropIfExists('institute_medicines');
        Schema::dropIfExists('medicine_identifiers');
        Schema::dropIfExists('medicine_product_ingredients');
        Schema::dropIfExists('medicine_products');
        Schema::dropIfExists('medicine_routes');
        Schema::dropIfExists('medicine_forms');
        Schema::dropIfExists('medicine_ingredients');
        Schema::dropIfExists('medicine_concepts');
    }
};
