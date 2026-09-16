<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_items', 'generic_name_snapshot')) {
                $table->string('generic_name_snapshot', 150)->nullable()->after('display_name_snapshot');
            }
            if (! Schema::hasColumn('prescription_items', 'unit_snapshot')) {
                $table->string('unit_snapshot', 20)->nullable()->after('dosage_form_snapshot');
            }
            if (! Schema::hasColumn('prescription_items', 'pack_size_snapshot')) {
                $table->unsignedInteger('pack_size_snapshot')->nullable()->after('unit_snapshot');
            }
            if (! Schema::hasColumn('prescription_items', 'category_snapshot')) {
                $table->string('category_snapshot', 100)->nullable()->after('pack_size_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn([
                'generic_name_snapshot',
                'unit_snapshot',
                'pack_size_snapshot',
                'category_snapshot',
            ]);
        });
    }
};
