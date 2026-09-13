<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `email` varchar(150) NULL');
    }
    public function down(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `email` varchar(150) NOT NULL');
    }
};
