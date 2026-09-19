<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_orders', 'sample_id')) {
                $table->unsignedBigInteger('sample_id')->nullable()->after('patient_id');
                $table->foreign('sample_id')->references('id')->on('lab_samples')->onDelete('set null');
            }
            if (! Schema::hasColumn('lab_orders', 'accession_number')) {
                $table->string('accession_number', 50)->nullable()->after('order_number');
                $table->index('accession_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            if (Schema::hasColumn('lab_orders', 'sample_id')) {
                $table->dropForeign(['sample_id']);
                $table->dropColumn('sample_id');
            }
            if (Schema::hasColumn('lab_orders', 'accession_number')) {
                $table->dropIndex(['accession_number']);
                $table->dropColumn('accession_number');
            }
        });
    }
};
