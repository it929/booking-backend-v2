<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doctor_schedules')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('doctor_schedules', 'day_of_week')) {
                    $table->string('day_of_week', 50)->default('Mon')->index()->after('doctor_id');
                }
                if (!Schema::hasColumn('doctor_schedules', 'shift_name')) {
                    $table->string('shift_name')->default('Consultation Clinic')->after('day_of_week');
                }
                if (!Schema::hasColumn('doctor_schedules', 'start_time')) {
                    $table->time('start_time')->default('08:00:00')->after('shift_name');
                }
                if (!Schema::hasColumn('doctor_schedules', 'end_time')) {
                    $table->time('end_time')->default('14:00:00')->after('start_time');
                }
                if (!Schema::hasColumn('doctor_schedules', 'slot_duration_minutes')) {
                    $table->unsignedInteger('slot_duration_minutes')->default(30)->after('capacity');
                }
            });
        }
    }

    public function down(): void
    {
        // No-op for safety
    }
};
