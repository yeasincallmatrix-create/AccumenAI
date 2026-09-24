<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutes') && ! Schema::hasColumn('institutes', 'subcategory_key')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->string('subcategory_key', 60)->nullable()->after('sub_industry');
                $table->index('subcategory_key', 'institutes_subcategory_key_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'subcategory_key')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->dropIndex('institutes_subcategory_key_index');
                $table->dropColumn('subcategory_key');
            });
        }
    }
};
