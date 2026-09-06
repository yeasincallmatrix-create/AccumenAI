<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------- PATIENTS --------
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('mr_number', 20)->unique();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('phone', 20);
            $table->string('email', 100)->nullable();
            $table->text('present_address')->nullable();
            $table->unsignedBigInteger('present_country_id')->nullable();
            $table->unsignedBigInteger('present_admin_1_id')->nullable();
            $table->unsignedBigInteger('present_admin_2_id')->nullable();
            $table->unsignedBigInteger('present_admin_3_id')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('present_country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('present_admin_1_id')->references('id')->on('administrative_units')->onDelete('set null');
            $table->foreign('present_admin_2_id')->references('id')->on('administrative_units')->onDelete('set null');
            $table->foreign('present_admin_3_id')->references('id')->on('administrative_units')->onDelete('set null');

            $table->index('institute_id');
            $table->index('mr_number');
            $table->index('phone');
        });

        // -------- WARDS --------
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100);
            $table->enum('type', ['general', 'cabin', 'icu', 'ccu', 'nicu', 'private']);
            $table->integer('total_beds')->default(0);
            $table->integer('available_beds')->default(0);
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index('institute_id');
        });

        // -------- BEDS --------
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('ward_id');
            $table->string('bed_number', 20);
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade');
            $table->unique(['ward_id', 'bed_number']);
            $table->index('institute_id');
            $table->index('status');
        });

        // -------- ADMISSIONS --------
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('bed_id')->nullable();
            $table->unsignedBigInteger('admitting_doctor_id');
            $table->date('admission_date');
            $table->time('admission_time');
            $table->text('primary_diagnosis')->nullable();
            $table->text('secondary_diagnosis')->nullable();
            $table->enum('status', ['active', 'discharged', 'transferred', 'expired'])->default('active');
            $table->date('discharge_date')->nullable();
            $table->time('discharge_time')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('discharged_by')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('bed_id')->references('id')->on('beds')->onDelete('set null');
            $table->foreign('admitting_doctor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('discharged_by')->references('id')->on('users')->onDelete('set null');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index('admission_date');
        });

        // -------- APPOINTMENTS --------
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->integer('serial_number')->nullable();
            $table->enum('status', ['scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('complaints')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('appointment_date');
            $table->index('status');
        });

        // -------- MEDICINES --------
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code', 50)->unique();
            $table->string('generic_name', 150);
            $table->string('brand_name', 150)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('dosage_form', 50);
            $table->string('strength', 50)->nullable();
            $table->string('unit', 20);
            $table->integer('pack_size')->default(1);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->integer('reorder_level')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('storage_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index('institute_id');
            $table->index('generic_name');
            $table->index('code');
        });

        // -------- PHARMACY STOCK --------
        Schema::create('pharmacy_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('batch_number', 50);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->integer('quantity_received')->default(0);
            $table->integer('current_quantity')->default(0);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->string('supplier_invoice_no', 50)->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('medicine_id')->references('id')->on('medicines')->onDelete('cascade');
            $table->unique(['medicine_id', 'batch_number']);
            $table->index('institute_id');
            $table->index('expiry_date');
            $table->index('current_quantity');
        });

        // -------- PRESCRIPTIONS --------
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->string('prescription_number', 50)->unique();
            $table->date('prescription_date');
            $table->text('diagnosis')->nullable();
            $table->text('chief_complaints')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('advice')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('prescription_number');
            $table->index('prescription_date');
        });

        // -------- PRESCRIPTION ITEMS --------
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('medicine_name', 200);
            $table->string('dosage', 50);
            $table->string('frequency', 50);
            $table->integer('duration_days')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'dispensed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->foreign('medicine_id')->references('id')->on('medicines')->onDelete('set null');

            $table->index('prescription_id');
            $table->index('status');
        });

        // -------- PHARMACY DISPENSES --------
        Schema::create('pharmacy_dispenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('prescription_item_id');
            $table->unsignedBigInteger('stock_id');
            $table->integer('quantity_dispensed')->default(1);
            $table->unsignedBigInteger('dispensed_by');
            $table->date('dispense_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('prescription_item_id')->references('id')->on('prescription_items')->onDelete('cascade');
            $table->foreign('stock_id')->references('id')->on('pharmacy_stock')->onDelete('cascade');
            $table->foreign('dispensed_by')->references('id')->on('users')->onDelete('cascade');

            $table->index('institute_id');
            $table->index('prescription_item_id');
            $table->index('dispense_date');
        });

        // -------- LAB TESTS --------
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('normal_range')->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index('institute_id');
            $table->index('code');
        });

        // -------- LAB ORDERS --------
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('prescription_id')->nullable();
            $table->string('order_number', 50)->unique();
            $table->date('order_date');
            $table->enum('priority', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', ['ordered', 'collected', 'processing', 'completed', 'cancelled'])->default('ordered');
            $table->text('clinical_notes')->nullable();
            $table->text('result_notes')->nullable();
            $table->datetime('collected_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('set null');
            $table->foreign('collected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('order_number');
            $table->index('status');
        });

        // -------- LAB RESULTS --------
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_order_id');
            $table->unsignedBigInteger('lab_test_id');
            $table->string('result_value', 255)->nullable();
            $table->text('result_text')->nullable();
            $table->text('normal_range')->nullable();
            $table->enum('status', ['pending', 'normal', 'abnormal', 'critical'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('cascade');
            $table->foreign('lab_test_id')->references('id')->on('lab_tests')->onDelete('cascade');

            $table->index('lab_order_id');
            $table->index('status');
        });

        // -------- MEDICAL INVOICES --------
        // NOTE (Phase 0): named `medical_invoices` — NOT `invoices` — because
        // this codebase already has a finance-module `invoices` table
        // (student/course billing). The Medical\Invoice model maps here.
        Schema::create('medical_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('type', ['opd', 'ipd', 'pharmacy', 'lab', 'surgery']);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('due_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'paid', 'partial', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'mobile_banking', 'tpa', 'other'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('set null');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('invoice_number');
            $table->index('status');
        });

        // -------- TPA CLAIMS --------
        Schema::create('tpa_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('claim_number', 50)->unique();
            $table->string('tpa_company_name', 150);
            $table->string('policy_number', 50);
            $table->decimal('claim_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'partial', 'settled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->date('claim_date');
            $table->date('approval_date')->nullable();
            $table->date('settlement_date')->nullable();
            $table->text('documents')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('medical_invoices')->onDelete('cascade');

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('claim_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpa_claims');
        Schema::dropIfExists('medical_invoices');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('pharmacy_dispenses');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('pharmacy_stock');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('patients');
    }
};
