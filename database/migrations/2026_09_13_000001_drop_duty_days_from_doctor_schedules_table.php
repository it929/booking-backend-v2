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
                if (Schema::hasColumn('doctor_schedules', 'duty_days')) {
                    $table->dropColumn('duty_days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doctor_schedules')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('doctor_schedules', 'duty_days')) {
                    $table->json('duty_days')->nullable()->after('shift_time');
                }
            });
        }
    }
};
