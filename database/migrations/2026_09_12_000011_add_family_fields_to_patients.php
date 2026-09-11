<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Family-aware patients: identity stays mr_number (already
     * phone-independent); these columns let several people share one
     * contact phone (e.g. parent + child) with an explicit relation.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('relation_to_primary', 20)->nullable()->after('gender');
            $table->unsignedBigInteger('primary_contact_id')->nullable()->after('relation_to_primary');
            $table->boolean('is_dependent')->default(false)->after('primary_contact_id');

            $table->foreign('primary_contact_id')->references('id')->on('patients')->onDelete('set null');
            $table->index(['primary_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['primary_contact_id']);
            $table->dropIndex(['primary_contact_id']);
            $table->dropColumn(['relation_to_primary', 'primary_contact_id', 'is_dependent']);
        });
    }
};
