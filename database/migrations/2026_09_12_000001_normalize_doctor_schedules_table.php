<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doctor_schedules')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('doctor_schedules', 'recurrence_type')) {
                    $table->string('recurrence_type', 30)->default('every')->after('day_of_week');
                }
                if (!Schema::hasColumn('doctor_schedules', 'recurrence_weeks')) {
                    $table->json('recurrence_weeks')->nullable()->after('recurrence_type');
                }
            });

            // Clean up and normalize existing records
            $schedules = DB::table('doctor_schedules')->get();
            $daysMap = [
                'monday' => 'Mon', 'mon' => 'Mon',
                'tuesday' => 'Tue', 'tue' => 'Tue',
                'wednesday' => 'Wed', 'wed' => 'Wed',
                'thursday' => 'Thu', 'thu' => 'Thu',
                'friday' => 'Fri', 'fri' => 'Fri',
                'saturday' => 'Sat', 'sat' => 'Sat',
                'sunday' => 'Sun', 'sun' => 'Sun',
            ];

            foreach ($schedules as $sched) {
                $raw = strtolower(trim($sched->day_of_week ?? ''));
                $detectedDay = 'Mon';
                foreach ($daysMap as $needle => $short) {
                    if (str_contains($raw, $needle)) {
                        $detectedDay = $short;
                        break;
                    }
                }

                $recType = 'every';
                $recWeeks = null;

                if (str_contains($raw, '1st') && str_contains($raw, '3rd')) {
                    $recType = '1st_and_3rd';
                    $recWeeks = json_encode([1, 3]);
                } elseif (str_contains($raw, '2nd') && str_contains($raw, '4th')) {
                    $recType = '2nd_and_4th';
                    $recWeeks = json_encode([2, 4]);
                } elseif (str_contains($raw, '1st') && str_contains($raw, '4th')) {
                    $recType = '1st_and_4th';
                    $recWeeks = json_encode([1, 4]);
                } elseif (str_contains($raw, '1st') && str_contains($raw, '2nd')) {
                    $recType = '1st_and_2nd';
                    $recWeeks = json_encode([1, 2]);
                } elseif (str_contains($raw, '3rd') && str_contains($raw, '4th')) {
                    $recType = '3rd_and_4th';
                    $recWeeks = json_encode([3, 4]);
                } elseif (str_contains($raw, '1st')) {
                    $recType = '1st_only';
                    $recWeeks = json_encode([1]);
                } elseif (str_contains($raw, '2nd')) {
                    $recType = '2nd_only';
                    $recWeeks = json_encode([2]);
                } elseif (str_contains($raw, '3rd')) {
                    $recType = '3rd_only';
                    $recWeeks = json_encode([3]);
                } elseif (str_contains($raw, '4th')) {
                    $recType = '4th_only';
                    $recWeeks = json_encode([4]);
                }

                DB::table('doctor_schedules')
                    ->where('id', $sched->id)
                    ->update([
                        'day_of_week' => $detectedDay,
                        'recurrence_type' => $recType,
                        'recurrence_weeks' => $recWeeks,
                        'duty_days' => json_encode([$detectedDay]),
                    ]);
            }

            // Ensure index exists on (day_of_week, status)
            if (!Schema::hasIndex('doctor_schedules', 'idx_sched_day_status')) {
                Schema::table('doctor_schedules', function (Blueprint $table) {
                    $table->index(['day_of_week', 'status'], 'idx_sched_day_status');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doctor_schedules')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('doctor_schedules', 'recurrence_weeks')) {
                    $table->dropColumn('recurrence_weeks');
                }
                if (Schema::hasColumn('doctor_schedules', 'recurrence_type')) {
                    $table->dropColumn('recurrence_type');
                }
            });
        }
    }
};
