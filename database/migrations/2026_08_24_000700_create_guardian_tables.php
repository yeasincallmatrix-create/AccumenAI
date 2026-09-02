<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 47 — Parent / Guardian Portal core.
 *
 * A dedicated, tenant-scoped guardian account can be linked to many students
 * (student_guardians). Guardians authenticate through their own guard and are
 * strictly read-only inside the portal. The audit_logs.user_type enum is
 * extended (additively) so guardian actions can be traced on the same
 * append-only audit trail as platform admins / institute users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                ->constrained('institutes')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('password_hash', 255);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('preferred_language', 5)->default('en');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->unsignedTinyInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id'], 'idx_guardians_institute');
            $table->unique(['institute_id', 'phone'], 'uq_guardians_institute_phone');
            $table->unique(['institute_id', 'email'], 'uq_guardians_institute_email');
            $table->index(['status'], 'idx_guardians_status');
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                ->constrained('institutes')
                ->cascadeOnDelete();
            $table->foreignId('guardian_id')
                ->constrained('guardians')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->enum('relationship', ['father', 'mother', 'guardian', 'other'])->default('guardian');
            $table->boolean('is_primary')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id'], 'uq_student_guardians_pair');
            $table->index(['institute_id', 'student_id'], 'idx_student_guardians_student');
            $table->index(['guardian_id', 'status'], 'idx_student_guardians_active');
        });

        $this->extendAuditUserTypeEnum();
    }

    private function extendAuditUserTypeEnum(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $column = DB::selectOne('SHOW COLUMNS FROM `audit_logs` LIKE \'user_type\'');
        $type = $column->Type ?? '';

        if (str_contains($type, 'guardian')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `audit_logs` MODIFY `user_type` ENUM('platform_admin','institute_user','guardian') NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
    }
};
