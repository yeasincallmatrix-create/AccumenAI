<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_type_id')->nullable()->after('batch_id');
            $table->foreign('certificate_type_id')->references('id')->on('certificate_types')->nullOnDelete();
            $table->index('certificate_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['certificate_type_id']);
            $table->dropIndex(['certificate_type_id']);
            $table->dropColumn('certificate_type_id');
        });
    }
};
