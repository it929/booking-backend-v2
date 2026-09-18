<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 50)->unique()->index();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('hmo_company_id')->nullable()->constrained('hmo_companies')->nullOnDelete();
            $table->date('appointment_date')->index();
            $table->string('appointment_time', 100);
            $table->string('patient_name');
            $table->string('patient_phone');
            $table->string('patient_email')->nullable();
            $table->text('reason')->nullable();
            $table->string('payment_type', 100)->default('Private Self-Pay');
            $table->string('hmo_policy_code', 100)->nullable();
            $table->string('hmo_auth_code', 100)->nullable();
            $table->string('hmo_status', 100)->default('N/A');
            $table->string('referral_doc_name')->nullable();
            $table->longText('referral_doc_data')->nullable();
            $table->text('referral_doc_text')->nullable();
            $table->string('payment_status', 100)->default('Pending');
            $table->string('payment_method', 100)->default('POS / Cash');
            $table->string('invoice_ref', 100)->nullable();
            $table->string('status', 100)->default('Confirmed')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('delete_reason')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            // Composite indexes for fast query execution on daily schedules & doctor slots
            $table->index(['doctor_id', 'appointment_date', 'is_active'], 'idx_doctor_date_active');
            $table->index(['appointment_date', 'status'], 'idx_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
