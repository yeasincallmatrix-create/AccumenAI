<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cover both '' (empty string, seen in prod) and NULL.
        DB::table('institutes')
            ->where(function ($q) {
                $q->whereNull('business_entity_type')
                    ->orWhere('business_entity_type', '');
            })
            ->update(['business_entity_type' => 'single_entity']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
