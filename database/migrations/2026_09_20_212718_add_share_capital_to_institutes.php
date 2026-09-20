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
        Schema::table('institutes', function (Blueprint $table) {
            if (! Schema::hasColumn('institutes', 'authorized_capital')) {
                $table->decimal('authorized_capital', 15, 2)->nullable()->after('business_entity_type');
            }
            if (! Schema::hasColumn('institutes', 'issued_capital')) {
                $table->decimal('issued_capital', 15, 2)->nullable()->after('authorized_capital');
            }
            if (! Schema::hasColumn('institutes', 'paid_up_capital')) {
                $table->decimal('paid_up_capital', 15, 2)->nullable()->after('issued_capital');
            }
            if (! Schema::hasColumn('institutes', 'shares_outstanding')) {
                $table->unsignedBigInteger('shares_outstanding')->nullable()->after('paid_up_capital');
            }
            if (! Schema::hasColumn('institutes', 'share_face_value')) {
                $table->decimal('share_face_value', 10, 2)->nullable()->default(10)->after('shares_outstanding');
            }
            if (! Schema::hasColumn('institutes', 'incorporation_date')) {
                $table->date('incorporation_date')->nullable()->after('share_face_value');
            }
            if (! Schema::hasColumn('institutes', 'registration_no')) {
                $table->string('registration_no', 50)->nullable()->after('incorporation_date');
            }
        });

        Schema::create('share_capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['issuance', 'transfer', 'buyback', 'bonus', 'rights']);
            $table->foreignId('shareholder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_shareholder_id')->nullable()->constrained('shareholders')->nullOnDelete();
            $table->foreignId('to_shareholder_id')->nullable()->constrained('shareholders')->nullOnDelete();
            $table->integer('shares');
            $table->decimal('face_value', 10, 2);
            $table->decimal('premium_per_share', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->date('transaction_date');
            $table->string('certificate_no', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'type']);
            $table->index(['institute_id', 'transaction_date']);
        });

        Schema::create('share_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shareholder_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_no', 50);
            $table->integer('shares');
            $table->decimal('face_value', 10, 2);
            $table->decimal('total_value', 15, 2);
            $table->date('issue_date');
            $table->date('cancel_date')->nullable();
            $table->enum('status', ['active', 'cancelled', 'transferred'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'certificate_no']);
            $table->index(['institute_id', 'shareholder_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_certificates');
        Schema::dropIfExists('share_capital_transactions');

        Schema::table('institutes', function (Blueprint $table) {
            $table->dropColumn([
                'authorized_capital', 'issued_capital', 'paid_up_capital',
                'shares_outstanding', 'share_face_value',
                'incorporation_date', 'registration_no',
            ]);
        });
    }
};
