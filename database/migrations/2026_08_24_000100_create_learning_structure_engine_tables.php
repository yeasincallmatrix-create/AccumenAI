<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Learning Structure Engine (additive, no breaking changes).
 *
 * Creates:
 *  - structure_label_dictionary (controlled vocabulary)
 *  - structure_templates (global + institute-private templates)
 *  - structure_template_levels (N-level definitions per template)
 *  - structure_nodes (generic N-level tree)
 *  - industry_template_mappings (country+industry → template)
 *  - student_placement_nodes (placement → nodes, N-level bridge)
 *  - institute_settings.structure_template_id (nullable FK)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Label dictionary — controlled vocabulary (§2.1)
        if (! Schema::hasTable('structure_label_dictionary')) {
            Schema::create('structure_label_dictionary', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->string('code', 80);
                $table->string('category', 40); // top_level|level_label|value_template
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['category', 'code'], 'sld_category_code_unique');
                $table->index(['category', 'status'], 'sld_category_status_idx');
            });
        }

        // 2. Templates (§3)
        if (! Schema::hasTable('structure_templates')) {
            Schema::create('structure_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 80);
                $table->string('description', 500)->nullable();
                $table->boolean('is_global')->default(true);
                $table->foreignId('institute_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['code', 'is_global', 'institute_id'], 'st_code_global_institute_unique');
                $table->index(['is_global', 'status'], 'st_global_status_idx');
                $table->index(['institute_id', 'status'], 'st_institute_status_idx');
            });
        }

        // 3. Template levels (§4)
        if (! Schema::hasTable('structure_template_levels')) {
            Schema::create('structure_template_levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('structure_templates')->cascadeOnDelete();
                $table->unsignedInteger('level_order');
                $table->string('label', 80);
                $table->string('label_key', 80)->nullable();
                $table->boolean('required')->default(true);
                $table->boolean('has_values')->default(true);
                $table->string('value_source', 80)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['template_id', 'level_order'], 'stl_template_levelorder_unique');
                $table->index(['template_id', 'level_order'], 'stl_template_order_idx');
            });
        }

        // 4. Nodes — generic N-level tree (§5)
        if (! Schema::hasTable('structure_nodes')) {
            Schema::create('structure_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('template_id')->constrained('structure_templates')->cascadeOnDelete();
                $table->foreignId('template_level_id')->nullable()->constrained('structure_template_levels')->nullOnDelete();
                $table->foreignId('parent_node_id')->nullable()->constrained('structure_nodes')->nullOnDelete();
                $table->unsignedInteger('level_order');
                $table->string('name', 120);
                $table->string('code', 80)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->boolean('is_custom')->default(false);
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['institute_id'], 'sn_institute_idx');
                $table->index(['template_id'], 'sn_template_idx');
                $table->index(['template_id', 'level_order'], 'sn_template_level_idx');
                $table->index(['parent_node_id'], 'sn_parent_idx');
                $table->index(['branch_id'], 'sn_branch_idx');
                $table->index(['status'], 'sn_status_idx');
                $table->index(['institute_id', 'template_id', 'level_order'], 'sn_institute_template_level_idx');
                $table->index(['institute_id', 'parent_node_id'], 'sn_institute_parent_idx');
            });
        }

        // 5. Industry mappings (§7)
        if (! Schema::hasTable('industry_template_mappings')) {
            Schema::create('industry_template_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('industry', 60);
                $table->string('sub_industry', 60)->nullable();
                $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('structure_template_id')->constrained('structure_templates')->cascadeOnDelete();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['industry'], 'itm_industry_idx');
                $table->index(['sub_industry'], 'itm_sub_industry_idx');
                $table->index(['country_id'], 'itm_country_idx');
                $table->index(['structure_template_id'], 'itm_template_idx');
                $table->index(['priority'], 'itm_priority_idx');
                $table->index(['status'], 'itm_status_idx');
                $table->index(['industry', 'sub_industry', 'country_id', 'status'], 'itm_industry_sub_country_status_idx');
                $table->unique(['industry', 'sub_industry', 'country_id'], 'itm_industry_sub_country_unique');
            });
        }

        // 6. Placement bridge (§8)
        if (! Schema::hasTable('student_placement_nodes')) {
            Schema::create('student_placement_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_academic_placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->unsignedInteger('level_order');
                $table->foreignId('node_id')->constrained('structure_nodes')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['student_academic_placement_id', 'level_order'], 'spn_placement_level_unique');
                $table->unique(['student_academic_placement_id', 'node_id'], 'spn_placement_node_unique');
                $table->index(['node_id'], 'spn_node_idx');
            });
        }

        // 7. Institute settings FK (§9)
        Schema::table('institute_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_settings', 'structure_template_id')) {
                if (Schema::hasColumn('institute_settings', 'academic_unit_label')) {
                    $table->foreignId('structure_template_id')->nullable()->after('academic_unit_label')
                        ->constrained('structure_templates')->nullOnDelete();
                } else {
                    $table->foreignId('structure_template_id')->nullable()
                        ->constrained('structure_templates')->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (Schema::hasColumn('institute_settings', 'structure_template_id')) {
                try {
                    $table->dropForeign(['structure_template_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('structure_template_id');
            }
        });

        Schema::dropIfExists('student_placement_nodes');
        Schema::dropIfExists('industry_template_mappings');
        Schema::dropIfExists('structure_nodes');
        Schema::dropIfExists('structure_template_levels');
        Schema::dropIfExists('structure_templates');
        Schema::dropIfExists('structure_label_dictionary');
    }
};
