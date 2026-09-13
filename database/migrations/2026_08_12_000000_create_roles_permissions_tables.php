<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('module')->nullable();
            $table->timestamps(false);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps(false);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['role_id', 'permission_id']);
        });

        $roles = [
            ['slug' => 'institute-owner', 'name' => 'Institute Owner', 'is_system' => true],
            ['slug' => 'institute-admin', 'name' => 'Institute Admin', 'is_system' => true],
            ['slug' => 'branch-manager', 'name' => 'Branch Manager', 'is_system' => true],
            ['slug' => 'teacher', 'name' => 'Teacher', 'is_system' => true],
            ['slug' => 'accountant', 'name' => 'Accountant', 'is_system' => true],
            ['slug' => 'receptionist', 'name' => 'Receptionist', 'is_system' => true],
            ['slug' => 'exam-controller', 'name' => 'Exam Controller', 'is_system' => true],
        ];
        DB::table('roles')->insert($roles);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
