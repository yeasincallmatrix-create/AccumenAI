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
        // Manual queue position; NULL = never reordered (falls back to serial).
        Schema::table('appointments', function (Blueprint $table) {
            $table->integer('queue_order')->nullable()->after('serial_number');
        });

        Schema::create('queue_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('appointment_id');
            // Staff member who performed the action (institute_users.id).
            $table->unsignedBigInteger('user_id')->nullable();
            // Role class at action time: 'doctor', 'receptionist' or 'owner'.
            $table->string('user_type', 20);
            $table->string('actor_name', 150)->nullable();
            $table->integer('old_order')->nullable();
            $table->integer('new_order');
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->index(['institute_id', 'appointment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_audit_logs');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('queue_order');
        });
    }
};
