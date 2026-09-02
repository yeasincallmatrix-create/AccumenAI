<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code', 4)->nullable()->unique()->after('id');
        });

        // Backfill existing branches with random unique codes
        $branches = DB::table('branches')->get();
        foreach ($branches as $branch) {
            $code = $this->generateUniqueCode();
            DB::table('branches')->where('id', $branch->id)->update(['code' => $code]);
        }

        // Make NOT NULL after backfill (handle doctrine/dbal missing)
        try {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('code', 4)->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            try {
                DB::statement("ALTER TABLE `branches` MODIFY `code` VARCHAR(4) NOT NULL");
            } catch (\Throwable $e2) {
                // Leave nullable if both fail (e.g., still nulls) — not blocking
            }
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('branches')->where('code', $code)->exists());
        return $code;
    }
};
