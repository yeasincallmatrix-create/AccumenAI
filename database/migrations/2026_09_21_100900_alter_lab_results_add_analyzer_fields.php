<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_results', 'analyzer_id')) {
                $table->unsignedBigInteger('analyzer_id')->nullable()->after('lab_test_id');
                $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('set null');
            }
            if (! Schema::hasColumn('lab_results', 'lab_message_id')) {
                $table->unsignedBigInteger('lab_message_id')->nullable()->after('analyzer_id');
                $table->foreign('lab_message_id')->references('id')->on('lab_messages')->onDelete('set null');
            }
            if (! Schema::hasColumn('lab_results', 'unit')) {
                $table->string('unit', 30)->nullable()->after('result_value');
            }
            if (! Schema::hasColumn('lab_results', 'flag')) {
                $table->string('flag', 10)->nullable()->after('unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $drops = [];
            foreach (['analyzer_id', 'lab_message_id', 'unit', 'flag'] as $col) {
                if (Schema::hasColumn('lab_results', $col)) {
                    $drops[] = $col;
                }
            }
            foreach (['analyzer_id', 'lab_message_id'] as $fk) {
                if (Schema::hasColumn('lab_results', $fk)) {
                    $table->dropForeign([$fk]);
                }
            }
            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
