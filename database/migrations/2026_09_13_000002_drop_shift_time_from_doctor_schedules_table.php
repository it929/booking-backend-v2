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
                if (Schema::hasColumn('doctor_schedules', 'shift_time')) {
                    $table->dropColumn('shift_time');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doctor_schedules')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('doctor_schedules', 'shift_time')) {
                    $table->string('shift_time')->nullable()->after('end_time');
                }
            });
        }
    }
};
