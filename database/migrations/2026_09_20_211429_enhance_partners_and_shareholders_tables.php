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
        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'capital_account_id')) {
                $table->foreignId('capital_account_id')->nullable()
                    ->after('share_percent')
                    ->constrained('chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('partners', 'drawing_account_id')) {
                $table->foreignId('drawing_account_id')->nullable()
                    ->after('capital_account_id')
                    ->constrained('chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('partners', 'nid')) {
                $table->string('nid', 50)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('partners', 'address')) {
                $table->text('address')->nullable()->after('nid');
            }
        });

        Schema::table('shareholders', function (Blueprint $table) {
            if (! Schema::hasColumn('shareholders', 'nid')) {
                $table->string('nid', 50)->nullable()->after('email');
            }
            if (! Schema::hasColumn('shareholders', 'address')) {
                $table->text('address')->nullable()->after('nid');
            }
            if (! Schema::hasColumn('shareholders', 'is_director')) {
                $table->boolean('is_director')->default(false)->after('share_percent');
            }
            if (! Schema::hasColumn('shareholders', 'director_designation')) {
                $table->string('director_designation', 50)->nullable()->after('is_director');
            }
            if (! Schema::hasColumn('shareholders', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('director_designation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropForeign(['capital_account_id']);
            $table->dropForeign(['drawing_account_id']);
            $table->dropColumn(['capital_account_id', 'drawing_account_id', 'nid', 'address']);
        });

        Schema::table('shareholders', function (Blueprint $table) {
            $table->dropColumn(['nid', 'address', 'is_director', 'director_designation', 'is_active']);
        });
    }
};
