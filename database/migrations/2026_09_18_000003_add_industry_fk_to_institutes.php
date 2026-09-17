<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_id')->nullable()->after('industry');
            $table->unsignedBigInteger('sub_industry_id')->nullable()->after('sub_industry');
            $table->index('industry_id');
            $table->index('sub_industry_id');
            $table->foreign('industry_id')->references('id')->on('industries')->onDelete('set null');
            $table->foreign('sub_industry_id')->references('id')->on('sub_industries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);
            $table->dropForeign(['sub_industry_id']);
            $table->dropIndex(['industry_id']);
            $table->dropIndex(['sub_industry_id']);
            $table->dropColumn(['industry_id', 'sub_industry_id']);
        });
    }
};
