<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('phone_2fa_otps')) {
            Schema::create('phone_2fa_otps', function (Blueprint $table) {
                $table->id();
                $table->string('guard', 20)->default('web');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('institute_id')->nullable();
                $table->string('phone', 20);
                $table->string('otp_hash', 255);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['guard', 'user_id', 'phone']);
                $table->index(['institute_id']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_2fa_otps');
    }
};
