<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_subject_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_subject_id');
            $table->string('component_name', 80);
            $table->decimal('max_marks', 7, 2)->default(0);
            $table->decimal('weight', 5, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('exam_subject_id')->references('id')->on('exam_subjects')->onDelete('cascade');
            $table->index(['exam_subject_id', 'sort_order']);
        });

        // Optional JSON snapshot for flexibility vs strict table: keep components column as cache
        // Not adding to exam_subjects to keep separate table as primary source.

        if (!\Illuminate\Support\Facades\Schema::hasColumn('exam_results', 'component_marks')) {
            \Illuminate\Support\Facades\Schema::table('exam_results', function (Blueprint $table) {
                $table->json('component_marks')->nullable()->after('other_marks');
            });
        }

        // Backfill existing exam_subjects with components from legacy columns
        $this->backfillLegacyComponents();
    }

    private function backfillLegacyComponents(): void
    {
        $subjects = \Illuminate\Support\Facades\DB::table('exam_subjects')->get();
        foreach ($subjects as $es) {
            $comps = [];
            if ((float)$es->written_marks > 0) $comps[] = ['name'=>'Written','marks'=>$es->written_marks];
            if ((float)$es->practical_marks > 0) $comps[] = ['name'=>'Practical','marks'=>$es->practical_marks];
            if ((float)$es->viva_marks > 0) $comps[] = ['name'=>'Viva','marks'=>$es->viva_marks];
            if ((float)($es->attendance_marks ?? 0) > 0) $comps[] = ['name'=>'Attendance','marks'=>$es->attendance_marks];
            if ((float)($es->other_marks ?? 0) > 0) $comps[] = ['name'=>'Other','marks'=>$es->other_marks];
            // If no marks yet but subject exists, create default Practical+Viva placeholders
            if (empty($comps)) {
                $comps[] = ['name'=>'Practical','marks'=>50];
                $comps[] = ['name'=>'Viva','marks'=>20];
            }
            foreach ($comps as $idx=>$c) {
                \Illuminate\Support\Facades\DB::table('exam_subject_components')->insert([
                    'exam_subject_id' => $es->id,
                    'component_name' => $c['name'],
                    'max_marks' => $c['marks'],
                    'weight' => null,
                    'sort_order' => $idx,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('exam_results', 'component_marks')) {
            \Illuminate\Support\Facades\Schema::table('exam_results', function (Blueprint $table) {
                $table->dropColumn('component_marks');
            });
        }
        Schema::dropIfExists('exam_subject_components');
    }
};
