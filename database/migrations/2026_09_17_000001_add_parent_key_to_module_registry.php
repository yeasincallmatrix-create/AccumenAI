<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_registry', function (Blueprint $table) {
            $table->string('parent_key', 60)->nullable()->after('key');
            $table->string('icon', 50)->nullable()->after('sort_order');
            $table->boolean('coming_soon')->default(false)->after('icon');
            $table->string('index_route', 150)->nullable()->after('coming_soon');
        });
    }

    public function down(): void
    {
        Schema::table('module_registry', function (Blueprint $table) {
            $table->dropColumn(['parent_key', 'icon', 'coming_soon', 'index_route']);
        });
    }
};
