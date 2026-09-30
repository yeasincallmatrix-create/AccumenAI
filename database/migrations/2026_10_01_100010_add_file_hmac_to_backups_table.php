<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            // HMAC-SHA256 over the encrypted file bytes (tamper detection).
            $table->string('file_hmac', 64)->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('file_hmac');
        });
    }
};
