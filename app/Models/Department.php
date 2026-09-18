<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon_name',
        'location',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'dept_id',
        'doctor_count',
        'clinic_schedules',
        'operating_days',
        'operating_hours',
    ];

    public function getDeptIdAttribute(): string
    {
        return $this->code;
    }

    public function getDoctorCountAttribute(): int
    {
        if (array_key_exists('doctors_count', $this->attributes)) {
            return (int) $this->attributes['doctors_count'];
        }
        if ($this->relationLoaded('doctors')) {
            return $this->doctors->count();
        }
        return 0;
    }

    public function getClinicSchedulesAttribute(): array
    {
        $doctors = $this->relationLoaded('doctors') ? $this->doctors : $this->doctors()->with('schedules')->get();
        $dayMap = [];

        foreach ($doctors as $doc) {
            $schedules = $doc->relationLoaded('schedules') ? $doc->schedules : $doc->schedules()->get();
            foreach ($schedules as $s) {
                $day = $s->day_of_week;
                if (!$day) continue;
                $shiftStr = $s->shift_time ?: $s->formatted_shift ?: '08:00 AM – 02:00 PM';
                if (!isset($dayMap[$day])) {
                    $dayMap[$day] = [
                        'day' => $day,
                        'shift_times' => [$shiftStr],
                        'capacity' => (int) ($s->capacity ?: 15),
                        'doctors_count' => 1,
                    ];
                } else {
                    if (!in_array($shiftStr, $dayMap[$day]['shift_times'])) {
                        $dayMap[$day]['shift_times'][] = $shiftStr;
                    }
                    $dayMap[$day]['capacity'] += (int) ($s->capacity ?: 15);
                    $dayMap[$day]['doctors_count'] += 1;
                }
            }
        }

        $weekOrder = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
        $result = [];
        foreach ($dayMap as $data) {
            $result[] = [
                'day' => $data['day'],
                'shift_time' => implode(', ', $data['shift_times']),
                'capacity' => $data['capacity'],
                'doctors_count' => $data['doctors_count'],
            ];
        }

        usort($result, function ($a, $b) use ($weekOrder) {
            $orderA = 99;
            $orderB = 99;
            foreach ($weekOrder as $k => $idx) {
                if (stripos($a['day'], $k) !== false) { $orderA = $idx; break; }
            }
            foreach ($weekOrder as $k => $idx) {
                if (stripos($b['day'], $k) !== false) { $orderB = $idx; break; }
            }
            return $orderA - $orderB;
        });

        return $result;
    }

    public function getOperatingDaysAttribute(): array
    {
        return array_map(function ($s) {
            return $s['day'];
        }, $this->clinic_schedules);
    }

    public function getOperatingHoursAttribute(): string
    {
        $scheds = $this->clinic_schedules;
        if (empty($scheds)) {
            return '08:00 AM – 02:00 PM';
        }
        $times = array_unique(array_column($scheds, 'shift_time'));
        return implode(', ', $times);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
