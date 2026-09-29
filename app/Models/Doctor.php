<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'department_id',
        'name',
        'full_name',
        'surname',
        'middlename',
        'lastname',
        'acronym',
        'qualification',
        'bio',
        'image_url',
        'accepts_private',
        'accepts_hmo',
        'consultation_fee',
        'status',
    ];

    protected $casts = [
        'accepts_private' => 'boolean',
        'accepts_hmo' => 'boolean',
        'consultation_fee' => 'decimal:2',
        'status' => 'boolean',
    ];

    protected $appends = [
        'doc_id',
        'specialty',
        'qualifications',
        'image',
        'available_days',
        'shift_time',
        'formatted_shift',
        'time_slots',
        'room_number',
        'daily_capacity',
        'billing_category_label',
        'accepted_patient_types',
        'initial_name',
        'next_schedule',
        'fully_booked_dates',
        'closed_dates',
    ];

    public function getAcronymAttribute($value): ?string
    {
        if (!empty($value)) {
            $trimmed = trim($value);
            // Valid initials acronym: 1-5 letters, no spaces, no title like Dr./Mr./Miss
            if (!preg_match('/\s/i', $trimmed) && !preg_match('/^(dr|doctor|mr|mrs|ms|miss|prof)\.?/i', $trimmed) && strlen($trimmed) <= 5 && preg_match('/^[a-zA-Z]+$/', $trimmed)) {
                return strtoupper($trimmed);
            }
        }

        // Derive clean initials from name or full_name
        $raw = $this->attributes['full_name'] ?? ($this->attributes['name'] ?? '');
        $clean = preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $raw);
        $clean = trim($clean);

        $parts = preg_split('/[\s\-_.]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($parts as $part) {
            if (preg_match('/[a-zA-Z]/', $part, $matches)) {
                $initials .= strtoupper($matches[0]);
            }
        }
        return !empty($initials) ? $initials : 'DOC';
    }

    public function getInitialNameAttribute(): string
    {
        $raw = $this->attributes['name'] ?? ($this->attributes['full_name'] ?? '');
        $clean = preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $raw);
        $clean = trim($clean);

        $parts = preg_split('/[\s\-_.]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($parts as $part) {
            if (preg_match('/[a-zA-Z]/', $part, $matches)) {
                $initials .= strtoupper($matches[0]);
            }
        }
        if (empty($initials)) {
            $initials = 'DOC';
        }
        return 'Dr. ' . $initials;
    }

    public function getAcceptedPatientTypesAttribute(): array
    {
        $types = [];
        if ($this->accepts_private) {
            $types[] = 'Private Self-Pay';
        }
        if ($this->accepts_hmo) {
            $types[] = 'HMO Insurance';
        }
        return $types;
    }

    public function getNameAttribute($value): string
    {
        return static::formatDoctorTitle($value);
    }

    public function getFullNameAttribute($value): string
    {
        return static::formatDoctorTitle($value ?: ($this->attributes['name'] ?? ''));
    }

    public static function formatDoctorTitle(?string $name): string
    {
        if (empty($name)) {
            return '';
        }
        $trimmed = trim($name);
        if (preg_match('/^dr\.?\s*/i', $trimmed)) {
            return preg_replace('/^dr\.?\s*/i', 'Dr. ', $trimmed);
        }
        if (preg_match('/^(mr|mrs|miss|ms)\.?\s+/i', $trimmed)) {
            return preg_replace('/^(mr|mrs|miss|ms)\.?\s+/i', 'Dr. ', $trimmed);
        }
        return 'Dr. ' . $trimmed;
    }

    public function getDocIdAttribute(): string
    {
        return $this->code;
    }

    public function getSpecialtyAttribute(): string
    {
        if ($this->relationLoaded('department') && $this->department) {
            return $this->department->name;
        }
        return 'General Medicine';
    }

    public function getQualificationsAttribute(): string
    {
        return (string) ($this->qualification ?? '');
    }

    public function getImageAttribute(): string
    {
        return $this->image_url ?? '';
    }

    public function getCachedSchedules()
    {
        if (!$this->relationLoaded('schedules')) {
            $this->setRelation('schedules', $this->schedules()->get());
        }
        return $this->schedules;
    }

    public function getActiveScheduleAttribute()
    {
        $scheds = $this->getCachedSchedules();
        return $scheds->where('status', true)->first() ?: $scheds->first();
    }

    public function getAvailableDaysAttribute(): array
    {
        try {
            $scheds = $this->getCachedSchedules();
            $activeScheds = $scheds->where('status', true);
            
            $days = [];
            foreach ($activeScheds as $s) {
                $day = $s->day_of_week ?: 'Mon';
                $days[] = DoctorSchedule::formatScheduleDayLabel($day, $s->recurrence_type, $s->recurrence_weeks);
            }

            $days = array_values(array_unique(array_filter($days)));
            if (!empty($days)) {
                return $days;
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        $sched = $this->active_schedule;
        return $sched && is_array($sched->duty_days) ? $sched->duty_days : ['Mon', 'Wed', 'Fri'];
    }

    public function getShiftTimeAttribute(): string
    {
        try {
            $scheds = $this->getCachedSchedules();
            $active = $scheds->where('status', true);
            if ($active->isNotEmpty()) {
                $shiftsByDay = [];
                foreach ($active as $s) {
                    $shift = $s->shift_time ?: $s->formatted_shift;
                    if ($shift && $s->day_of_week) {
                        $shiftsByDay[$s->day_of_week] = $shift;
                    }
                }
                $uniqueShifts = array_unique(array_values($shiftsByDay));
                if (count($uniqueShifts) === 1) {
                    return $uniqueShifts[0];
                }
                if (count($uniqueShifts) > 1) {
                    $parts = [];
                    foreach ($shiftsByDay as $day => $sh) {
                        $parts[] = "{$day}: {$sh}";
                    }
                    return implode(' | ', $parts);
                }
                $first = $active->first();
                if ($first && !empty($first->shift_time)) {
                    return $first->shift_time;
                }
            }
        } catch (\Throwable $e) {}

        return '08:00 AM – 02:00 PM';
    }

    public function getFormattedShiftAttribute(): string
    {
        return $this->getShiftTimeAttribute();
    }

    public function getTimeSlotsAttribute(): array
    {
        try {
            $scheds = $this->getCachedSchedules();
            $activeSchedules = $scheds->where('status', true);
            if ($activeSchedules->isNotEmpty()) {
                $slots = [];
                foreach ($activeSchedules as $sched) {
                    if (!empty($sched->generated_slots)) {
                        foreach ($sched->generated_slots as $s) {
                            $slots[] = $s;
                        }
                    } else {
                        $slots[] = $sched->shift_time;
                    }
                }
                $unique = array_values(array_unique(array_filter($slots)));
                if (!empty($unique)) {
                    return $unique;
                }
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        $sched = $this->active_schedule;
        return $sched && !empty($sched->shift_time) ? [$sched->shift_time] : ['08:00 AM – 02:00 PM'];
    }

    public function scheduleForDay(string $dayOfWeek): ?DoctorSchedule
    {
        try {
            return $this->schedules()
                ->where('status', true)
                ->where('day_of_week', $dayOfWeek)
                ->first();
        } catch (\Throwable $e) {
            return $this->active_schedule;
        }
    }

    public function getRoomNumberAttribute(): string
    {
        return '';
    }

    public function getDailyCapacityAttribute(): int
    {
        $sched = $this->active_schedule;
        return $sched ? (int) $sched->capacity : 15;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function getBillingCategoryLabelAttribute(): string
    {
        $hasPriv = $this->accepts_private !== null ? (bool) $this->accepts_private : true;
        $hasHmo = $this->accepts_hmo !== null ? (bool) $this->accepts_hmo : true;

        if ($hasPriv && $hasHmo) return 'HMO & Private';
        if ($hasPriv) return 'Private Only';
        if ($hasHmo) return 'HMO Only';
        return 'HMO & Private';
    }

    public function scopeAcceptsHmo($query)
    {
        return $query->where('accepts_hmo', true);
    }

    public function scopeAcceptsPrivate($query)
    {
        return $query->where('accepts_private', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    protected static ?array $batchBookingsByDoctor = null;

    /**
     * Batch preload active booking counts for the next 60 days in a single fast query.
     */
    public static function preloadActiveBookings(?array $doctorIds = null): void
    {
        $today = Carbon::today();
        $maxDate = (clone $today)->addDays(60);

        $query = Booking::whereBetween('appointment_date', [$today->format('Y-m-d'), $maxDate->format('Y-m-d')])
            ->where('is_active', true)
            ->whereNotIn('status', ['Cancelled', 'Rejected', 'Deleted']);

        if (!empty($doctorIds)) {
            $query->whereIn('doctor_id', $doctorIds);
        }

        $rows = $query->selectRaw('doctor_id, DATE(appointment_date) as app_date, count(*) as count')
            ->groupBy('doctor_id', 'app_date')
            ->get();

        static::$batchBookingsByDoctor = [];
        foreach ($rows as $r) {
            static::$batchBookingsByDoctor[$r->doctor_id][$r->app_date] = (int) $r->count;
        }
    }

    /**
     * Helper to get active booking counts by date for a doctor (uses in-memory batch if preloaded).
     */
    public static function getBookingsByDateForDoctor(int $doctorId): array
    {
        if (static::$batchBookingsByDoctor !== null) {
            return static::$batchBookingsByDoctor[$doctorId] ?? [];
        }

        $today = Carbon::today();
        $maxDate = (clone $today)->addDays(60);

        return Booking::where('doctor_id', $doctorId)
            ->whereBetween('appointment_date', [$today->format('Y-m-d'), $maxDate->format('Y-m-d')])
            ->where('is_active', true)
            ->whereNotIn('status', ['Cancelled', 'Rejected', 'Deleted'])
            ->selectRaw('DATE(appointment_date) as app_date, count(*) as count')
            ->groupBy('app_date')
            ->pluck('count', 'app_date')
            ->toArray();
    }

    protected static ?array $candidateDates = null;
    protected ?array $computedAvailability = null;

    /**
     * Precompute and memoize the 60-day calendar dates once per request.
     */
    public static function getCandidateDates(): array
    {
        if (static::$candidateDates !== null) {
            return static::$candidateDates;
        }

        $today = Carbon::today();
        $dates = [];
        for ($i = 0; $i <= 60; $i++) {
            $c = (clone $today)->addDays($i);
            $dates[] = [
                'index' => $i,
                'carbon' => $c,
                'dayShort' => $c->format('D'),
                'dateStr' => $c->format('Y-m-d'),
                'formatted' => $c->format('D, j M Y'),
                'isToday' => ($i === 0),
            ];
        }

        return static::$candidateDates = $dates;
    }

    /**
     * Compute next schedule, fully booked dates, and closed dates in ONE single fast pass.
     */
    protected function computeScheduleAvailability(): array
    {
        if ($this->computedAvailability !== null) {
            return $this->computedAvailability;
        }

        $scheds = $this->getCachedSchedules();
        $activeScheds = $scheds->where('status', true);
        if ($activeScheds->isEmpty()) {
            return $this->computedAvailability = [
                'next_schedule' => null,
                'fully_booked_dates' => [],
                'closed_dates' => [],
            ];
        }

        $dates = static::getCandidateDates();
        $bookingsByDate = static::getBookingsByDateForDoctor($this->id);

        $nextSchedule = null;
        $fullyBookedDates = [];
        $closedDates = [];

        foreach ($dates as $d) {
            $candidate = $d['carbon'];
            $dayShort = $d['dayShort'];
            $dateStr = $d['dateStr'];
            $isToday = $d['isToday'];

            foreach ($activeScheds as $sched) {
                if (strcasecmp($sched->day_of_week, $dayShort) === 0 && $sched->isOnDutyOnDate($candidate)) {
                    $isClosedToday = $isToday && $sched->isBookingClosedForDate($candidate);
                    if ($isClosedToday) {
                        $closedDates[] = $dateStr;
                    }

                    $cap = (int) ($sched->capacity ?: ($this->daily_capacity ?: 20));
                    $booked = $bookingsByDate[$dateStr] ?? 0;
                    $isFullyBooked = ($booked >= $cap);

                    if ($isFullyBooked || $isClosedToday) {
                        $fullyBookedDates[] = $dateStr;
                    }

                    if ($nextSchedule === null && !$isClosedToday) {
                        $nextSchedule = [
                            'date' => $dateStr,
                            'formatted_date' => $d['formatted'],
                            'day_of_week' => $dayShort,
                            'capacity' => $cap,
                            'booked_count' => $booked,
                            'remaining_slots' => max(0, $cap - $booked),
                            'is_fully_booked' => $isFullyBooked,
                            'shift_time' => $sched->shift_time ?: $sched->formatted_shift ?: '08:00 AM – 02:00 PM',
                        ];
                    }

                    break;
                }
            }
        }

        return $this->computedAvailability = [
            'next_schedule' => $nextSchedule,
            'fully_booked_dates' => array_values(array_unique($fullyBookedDates)),
            'closed_dates' => array_values(array_unique($closedDates)),
        ];
    }

    /**
     * Get the next upcoming scheduled clinic date for this doctor on or after today,
     * along with real-time active bookings count, capacity, and fully booked status.
     */
    public function getNextScheduleAttribute(): ?array
    {
        return $this->computeScheduleAvailability()['next_schedule'];
    }

    /**
     * Get list of YYYY-MM-DD dates in the next 60 days that are fully booked or closed for this doctor.
     */
    public function getFullyBookedDatesAttribute(): array
    {
        return $this->computeScheduleAvailability()['fully_booked_dates'];
    }

    /**
     * Get list of YYYY-MM-DD dates where clinic booking is closed (e.g. today if within 10 minutes of start time or past).
     */
    public function getClosedDatesAttribute(): array
    {
        return $this->computeScheduleAvailability()['closed_dates'];
    }

    /**
     * Mask doctor full legal name details for unauthenticated public consumers.
     * Replaces name and full_name with initial_name (e.g. "Dr. O"), and sets surname, middlename, lastname to null.
     */
    public function maskForPublic(): self
    {
        $initial = $this->initial_name;
        $this->attributes['name'] = $initial;
        $this->attributes['full_name'] = $initial;
        $this->attributes['surname'] = null;
        $this->attributes['middlename'] = null;
        $this->attributes['lastname'] = null;
        return $this;
    }
}
