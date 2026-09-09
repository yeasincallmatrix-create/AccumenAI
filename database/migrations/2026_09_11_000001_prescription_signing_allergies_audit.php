<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signing metadata on the existing prescriptions table.
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('signed_at')->nullable()->after('is_finalized');
            $table->unsignedBigInteger('signed_by')->nullable()->after('signed_at');
            $table->string('signature_hash', 64)->nullable()->after('signed_by');
        });

        // Structured patient allergies (per-institute).
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('allergen_type', 20)->default('drug');
            $table->string('allergen_name', 160);
            $table->string('reaction', 255)->nullable();
            $table->string('severity', 20)->default('moderate');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->index(['institute_id', 'patient_id']);
        });

        // Audit trail for prescription lifecycle events.
        Schema::create('prescription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 30)->default('system');
            $table->string('actor_name', 160)->nullable();
            $table->string('action', 40);
            $table->string('detail', 255)->nullable();
            $table->timestamps();

            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->index(['institute_id', 'prescription_id']);
        });

        // Backfill structured rows from the legacy free-text allergies column.
        $this->backfillAllergies();
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_audit_logs');
        Schema::dropIfExists('patient_allergies');

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['signed_at', 'signed_by', 'signature_hash']);
        });
    }

    private function backfillAllergies(): void
    {
        $patients = DB::table('patients')
            ->select('id', 'institute_id', 'allergies')
            ->whereNotNull('allergies')
            ->where('allergies', '!=', '')
            ->get();

        $now = now();
        foreach ($patients as $patient) {
            $names = array_values(array_unique(array_filter(array_map(
                fn ($v) => trim((string) $v),
                explode(',', (string) $patient->allergies)
            ))));
            foreach ($names as $name) {
                DB::table('patient_allergies')->insert([
                    'institute_id' => $patient->institute_id,
                    'patient_id' => $patient->id,
                    'medicine_id' => null,
                    'allergen_type' => 'drug',
                    'allergen_name' => mb_substr($name, 0, 160),
                    'reaction' => null,
                    'severity' => 'moderate',
                    'is_verified' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
