<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align uuid column defaults with the rest of the project (DB-generated
 * uuid(), matching institutes / institute_users / platform_admins).
 * Additive; does not touch authentication or password columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // All existing users rows carry uuids (migrated from institute_users).
        DB::statement('ALTER TABLE `users` MODIFY `uuid` char(36) NOT NULL DEFAULT uuid()');

        DB::statement('ALTER TABLE `institution_user` MODIFY `uuid` char(36) NOT NULL DEFAULT uuid()');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `uuid` char(36) NULL DEFAULT NULL');

        DB::statement('ALTER TABLE `institution_user` MODIFY `uuid` char(36) NOT NULL');
    }
};
