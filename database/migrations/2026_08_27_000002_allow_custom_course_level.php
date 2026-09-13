<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Allow custom course levels (form says "type a custom level") and fix
 * Integrity constraint violation 1048 when level is null.
 *
 * Original column was enum('basic','intermediate','advanced','professional','diploma','higher_diploma','certificate')
 * NOT NULL DEFAULT 'basic'. Changed to VARCHAR(50) to support custom values while keeping NOT NULL default.
 * The NOT NULL constraint is preserved; application layer (CourseMasterService::normalize) now coerces null/empty to 'basic'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Use raw statement for reliable enum -> varchar conversion on MySQL/MariaDB
        DB::statement("ALTER TABLE courses MODIFY level VARCHAR(50) NOT NULL DEFAULT 'basic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE courses MODIFY level ENUM('basic','intermediate','advanced','professional','diploma','higher_diploma','certificate') NOT NULL DEFAULT 'basic'");
    }
};
