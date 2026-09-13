<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course Master content columns (Step 42).
 *
 * Completes the existing course master with the fields the legacy schema was
 * missing: banner, intro video, language, short description, display order,
 * SEO metadata and the ordered requirement / outcome / prerequisite lists
 * (stored as JSON, purely informational — no admission rule is invented).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('banner', 255)->nullable()->after('thumbnail');
            $table->string('intro_video', 500)->nullable()->after('banner');
            $table->string('language', 30)->nullable()->after('level');
            $table->string('short_description', 500)->nullable()->after('description');
            $table->unsignedInteger('display_order')->default(0)->after('is_featured');
            $table->string('meta_title', 200)->nullable()->after('display_order');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            $table->json('requirements')->nullable()->after('meta_keywords');
            $table->json('outcomes')->nullable()->after('requirements');
            $table->json('prerequisites')->nullable()->after('outcomes');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'banner', 'intro_video', 'language', 'short_description', 'display_order',
                'meta_title', 'meta_description', 'meta_keywords', 'requirements', 'outcomes', 'prerequisites',
            ]);
        });
    }
};
