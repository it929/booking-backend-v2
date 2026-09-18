<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('day_of_week', 50)->default('Mon')->index();
            $table->string('shift_name')->default('Consultation Clinic');
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('14:00:00');
            $table->string('shift_time')->nullable();
            $table->json('duty_days')->nullable();
            $table->unsignedInteger('capacity')->default(15);
            $table->unsignedInteger('slot_duration_minutes')->default(30);
            $table->string('room')->nullable()->default('');
            $table->json('day_configs')->nullable();
            $table->unsignedInteger('total_weekly_capacity')->nullable()->default(15);
            $table->boolean('status')->default(true)->index();
            $table->timestamps();

            // High performance composite index for daily doctor lookup
            $table->index(['doctor_id', 'day_of_week', 'status'], 'idx_doc_day_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
