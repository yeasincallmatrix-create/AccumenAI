<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * institution_user — the Membership pivot between the global users account
 * and institutions. Replaces institute_users (one-row-per-institute accounts)
 * with a many-to-many membership carrying a per-institution role, branch and
 * staff attributes.
 *
 * Additive only: institute_users is kept (deprecated) during the transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('branch_id')->nullable();

            // Staff attributes scoped to the membership (institution-specific).
            $table->string('employee_id', 40)->nullable();
            $table->string('designation', 80)->nullable();
            $table->string('department', 80)->nullable();
            $table->string('qualification', 150)->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->string('father_name', 120)->nullable();
            $table->string('mother_name', 120)->nullable();
            $table->string('religion', 30)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('nid_photo', 255)->nullable();

            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            // Traceability back to the source institute_users row (rollback).
            $table->unsignedBigInteger('legacy_institute_user_id')->nullable()->unique();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'institution_id']);

            $table->index('institution_id');
            $table->index('role_id');
            $table->index('branch_id');
            $table->index('status');

            $table->foreign('user_id', 'fk_institution_user_user')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('institution_id', 'fk_institution_user_institute')
                ->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('role_id', 'fk_institution_user_role')
                ->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('branch_id', 'fk_institution_user_branch')
                ->references('id')->on('branches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_user');
    }
};
