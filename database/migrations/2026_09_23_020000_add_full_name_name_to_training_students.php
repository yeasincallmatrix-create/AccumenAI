<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_students')) {
            Schema::table('training_students', function (Blueprint $table) {
                if (! Schema::hasColumn('training_students', 'full_name')) {
                    $table->string('full_name', 255)->nullable()->after('id');
                }
                if (! Schema::hasColumn('training_students', 'name')) {
                    $table->string('name', 255)->nullable()->after('full_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_students')) {
            Schema::table('training_students', function (Blueprint $table) {
                foreach (['full_name', 'name'] as $column) {
                    if (Schema::hasColumn('training_students', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
