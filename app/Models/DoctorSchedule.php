<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'doctor_id',
        'day_of_week',
        'recurrence_type',
        'recurrence_weeks',
        'shift_name',
        'start_time',
        'end_time',
        'capacity',
        'slot_duration_minutes',
        'room',
        'day_configs',
        'total_weekly_capacity',
        'status',
    ];

    protected $casts = [
        'recurrence_weeks' => 'array',
        'day_configs' => 'array',
        'capacity' => 'integer',
        'slot_duration_minutes' => 'integer',
        'total_weekly_capacity' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = [
        'sched_id',
        'doctor_name',
        'specialty',
        'shift_time',
        'formatted_shift',
        'generated_slots',
        'duty_days',
    ];

    public function getSchedIdAttribute(): string
    {
        return (string) ($this->code ?? $this->id ?? '');
    }

    public function getDoctorNameAttribute(): string
    {
        if ($this->relationLoaded('doctor') && $this->doctor) {
            return $this->doctor->full_name ?: $this->doctor->name;
        }
        return '';
    }

    public function getSpecialtyAttribute(): string
    {
        if ($this->relationLoaded('doctor') && $this->doctor) {
            return $this->doctor->specialty;
        }
        return 'General Medicine';
    }

    /**
     * Scope to find active schedules on a specific short day (e.g. 'Mon', 'Wed', 'Sat')
     */
    public function scopeActiveOnDay($query, string $dayShort)
    {
        return $query->where('status', true);
    }

    /**
     * Map an array of week numbers (1-5) to a standard recurrence_type string.
     */
    public static function weeksToRecurrenceType(array $weeks): string
    {
        $sorted = array_values(array_unique(array_map('intval', $weeks)));
        sort($sorted);
        if (empty($sorted) || count($sorted) >= 5) {
            return 'every';
        }
        $key = implode(',', $sorted);
        switch ($key) {
            case '1,4': return '1st_and_4th';
            case '1,3': return '1st_and_3rd';
            case '2,4': return '2nd_and_4th';
            case '1,2': return '1st_and_2nd';
            case '3,4': return '3rd_and_4th';
            case '1': return '1st_only';
            case '2': return '2nd_only';
            case '3': return '3rd_only';
            case '4': return '4th_only';
            case '5': return '5th_only';
            default: return 'custom';
        }
    }

    /**
     * Map a recurrence_type string to its default array of week numbers.
     */
    public static function recurrenceTypeToWeeks(string $type): ?array
    {
        switch ($type) {
            case '1st_and_4th': return [1, 4];
            case '1st_and_3rd': return [1, 3];
            case '2nd_and_4th': return [2, 4];
            case '1st_and_2nd': return [1, 2];
            case '3rd_and_4th': return [3, 4];
            case '1st_only': return [1];
            case '2nd_only': return [2];
            case '3rd_only': return [3];
            case '4th_only': return [4];
            case '5th_only': return [5];
            case 'every': return null;
            default: return null;
        }
    }

    /**
     * Format a display label for a clinic schedule day (e.g. "1st & 4th Fri", "1st & 3rd Sat").
     */
    public static function formatScheduleDayLabel(string $day, ?string $recType, ?array $recWeeks): string
    {
        $day = ucfirst(substr(trim($day), 0, 3));
        $weeks = !empty($recWeeks) ? array_values(array_unique(array_map('intval', $recWeeks))) : null;
        if (!empty($weeks)) {
            sort($weeks);
        }

        if (empty($weeks) && !empty($recType) && $recType !== 'every') {
            $weeks = self::recurrenceTypeToWeeks($recType);
        }

        if (!empty($weeks) && count($weeks) < 5) {
            $ordinalMap = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'];
            $ordinals = array_map(fn($w) => $ordinalMap[$w] ?? "{$w}th", $weeks);
            if (count($ordinals) === 1) {
                return "{$ordinals[0]} {$day}";
            }
            $last = array_pop($ordinals);
            return implode(', ', $ordinals) . " & {$last} {$day}";
        }

        return $day;
    }

    /**
     * Determines whether the doctor is on duty on a specific calendar date,
     * taking into account day of week and recurrence pattern (e.g. 1st & 4th Friday).
     */
    public function isOnDutyOnDate(Carbon $date): bool
    {
        if (!$this->status) {
            return false;
        }

        $dayShort = $date->format('D'); // 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'
        if (strcasecmp($this->day_of_week, $dayShort) !== 0) {
            return false;
        }

        $recType = $this->recurrence_type ?: 'every';
        $recWeeks = !empty($this->recurrence_weeks) && is_array($this->recurrence_weeks)
            ? array_values(array_unique(array_map('intval', $this->recurrence_weeks)))
            : self::recurrenceTypeToWeeks($recType);

        // If recurrence weeks are defined and less than all 5 weeks, check nth occurrence
        if (!empty($recWeeks) && count($recWeeks) > 0 && count($recWeeks) < 5) {
            $nthOccurrence = (int) ceil($date->day / 7);
            return in_array($nthOccurrence, $recWeeks);
        }

        if ($recType === 'every' || empty($recType)) {
            return true;
        }

        $nthOccurrence = (int) ceil($date->day / 7);
        switch ($recType) {
            case '1st_and_3rd':
                return in_array($nthOccurrence, [1, 3]);
            case '2nd_and_4th':
                return in_array($nthOccurrence, [2, 4]);
            case '1st_and_4th':
                return in_array($nthOccurrence, [1, 4]);
            case '1st_and_2nd':
                return in_array($nthOccurrence, [1, 2]);
            case '3rd_and_4th':
                return in_array($nthOccurrence, [3, 4]);
            case '1st_only':
                return $nthOccurrence === 1;
            case '2nd_only':
                return $nthOccurrence === 2;
            case '3rd_only':
                return $nthOccurrence === 3;
            case '4th_only':
                return $nthOccurrence === 4;
            case '5th_only':
                return $nthOccurrence === 5;
            default:
                return true;
        }
    }

    /**
     * Determines whether online bookings are closed for this schedule on a given date.
     * When the date is today, bookings close 10 minutes prior to the clinic start_time.
     */
    public function isBookingClosedForDate(Carbon $date): bool
    {
        if (!$date->isToday()) {
            return false;
        }

        $startTimeStr = (string) ($this->start_time ?: '08:00:00');
        try {
            $parts = explode(':', $startTimeStr);
            $hours = isset($parts[0]) ? (int) $parts[0] : 8;
            $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

            $clinicStartTime = Carbon::today()->setTime($hours, $minutes, 0);
            $cutoffTime = $clinicStartTime->copy()->subMinutes(10);

            return now()->greaterThanOrEqualTo($cutoffTime);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the 10-minute cut-off Carbon time for today's clinic commencement.
     */
    public function getCutoffTimeForToday(): ?Carbon
    {
        $startTimeStr = (string) ($this->start_time ?: '08:00:00');
        try {
            $parts = explode(':', $startTimeStr);
            $hours = isset($parts[0]) ? (int) $parts[0] : 8;
            $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

            $clinicStartTime = Carbon::today()->setTime($hours, $minutes, 0);
            return $clinicStartTime->copy()->subMinutes(10);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parses a day string (which might contain ordinals like '1st & 4th Fri') or config arrays
     * into normalized components: [day_of_week, recurrence_type, recurrence_weeks]
     */
    public static function normalizeDayAndRecurrence(string $rawDay, ?string $presetType = null, ?array $presetWeeks = null): array
    {
        $raw = strtolower(trim($rawDay));
        $daysMap = [
            'monday' => 'Mon', 'mon' => 'Mon',
            'tuesday' => 'Tue', 'tue' => 'Tue',
            'wednesday' => 'Wed', 'wed' => 'Wed',
            'thursday' => 'Thu', 'thu' => 'Thu',
            'friday' => 'Fri', 'fri' => 'Fri',
            'saturday' => 'Sat', 'sat' => 'Sat',
            'sunday' => 'Sun', 'sun' => 'Sun',
        ];

        $dayOfWeek = 'Mon';
        foreach ($daysMap as $needle => $short) {
            if (str_contains($raw, $needle)) {
                $dayOfWeek = $short;
                break;
            }
        }

        $recWeeks = !empty($presetWeeks) ? array_values(array_unique(array_map('intval', $presetWeeks))) : null;
        if (!empty($recWeeks)) {
            sort($recWeeks);
        }

        $recType = $presetType;

        // 1. If presetWeeks provided:
        if (!empty($recWeeks)) {
            if (empty($recType) || $recType === 'every') {
                $recType = self::weeksToRecurrenceType($recWeeks);
            }
        }

        // 2. If presetType provided but no presetWeeks:
        if (!empty($recType) && $recType !== 'every' && empty($recWeeks)) {
            $recWeeks = self::recurrenceTypeToWeeks($recType);
        }

        // 3. If still not determined, parse from rawDay string:
        if ((empty($recType) || $recType === 'every') && empty($recWeeks)) {
            $parsedWeeks = [];
            if (str_contains($raw, '1st')) $parsedWeeks[] = 1;
            if (str_contains($raw, '2nd')) $parsedWeeks[] = 2;
            if (str_contains($raw, '3rd')) $parsedWeeks[] = 3;
            if (str_contains($raw, '4th')) $parsedWeeks[] = 4;
            if (str_contains($raw, '5th')) $parsedWeeks[] = 5;

            if (!empty($parsedWeeks)) {
                $recWeeks = $parsedWeeks;
                $recType = self::weeksToRecurrenceType($parsedWeeks);
            } else {
                $recType = 'every';
                $recWeeks = null;
            }
        }

        // Final normalization: if recurrence_type is 'every', ensure recWeeks is null
        if ($recType === 'every' && (empty($recWeeks) || count($recWeeks) >= 5)) {
            $recWeeks = null;
        }

        return [$dayOfWeek, $recType ?: 'every', $recWeeks];
    }

    /**
     * Backward-compatible accessor: s.duty_days returns an array with the formatted day name
     */
    public function getDutyDaysAttribute($value): array
    {
        $day = $this->day_of_week ?: 'Mon';
        $formatted = self::formatScheduleDayLabel($day, $this->recurrence_type, $this->recurrence_weeks);
        return [$formatted];
    }

    /**
     * Backward-compatible accessor: $schedule->shift_time returns formatted shift string (e.g. 08:00 AM – 02:00 PM)
     * derived directly from start_time and end_time.
     */
    public function getShiftTimeAttribute(): string
    {
        return $this->getFormattedShiftAttribute();
    }

    public function getFormattedShiftAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return '08:00 AM – 02:00 PM';
        }

        $sParts = explode(':', (string) $this->start_time);
        $eParts = explode(':', (string) $this->end_time);
        if (count($sParts) >= 2 && count($eParts) >= 2) {
            $h1 = (int) $sParts[0];
            $m1 = (int) $sParts[1];
            $p1 = $h1 >= 12 ? 'PM' : 'AM';
            $h1F = $h1 % 12 === 0 ? 12 : $h1 % 12;

            $h2 = (int) $eParts[0];
            $m2 = (int) $eParts[1];
            $p2 = $h2 >= 12 ? 'PM' : 'AM';
            $h2F = $h2 % 12 === 0 ? 12 : $h2 % 12;

            return sprintf('%02d:%02d %s – %02d:%02d %s', $h1F, $m1, $p1, $h2F, $m2, $p2);
        }

        try {
            $start = Carbon::parse($this->start_time)->format('h:i A');
            $end = Carbon::parse($this->end_time)->format('h:i A');
            return "{$start} – {$end}";
        } catch (\Exception $e) {
            return '08:00 AM – 02:00 PM';
        }
    }

    /**
     * Generates discrete bookable appointment slots between start_time and end_time
     */
    public function getGeneratedSlotsAttribute(): array
    {
        if (!$this->start_time || !$this->end_time) {
            return [$this->getFormattedShiftAttribute()];
        }

        $sParts = explode(':', (string) $this->start_time);
        $eParts = explode(':', (string) $this->end_time);
        if (count($sParts) >= 2 && count($eParts) >= 2) {
            $startM = (int) $sParts[0] * 60 + (int) $sParts[1];
            $endM = (int) $eParts[0] * 60 + (int) $eParts[1];
            if ($endM > $startM) {
                $duration = max(15, (int) ($this->slot_duration_minutes ?: 30));
                $slots = [];
                for ($cur = $startM; $cur + $duration <= $endM; $cur += $duration) {
                    $curEnd = $cur + $duration;

                    $h1 = (int) floor($cur / 60);
                    $m1 = (int) ($cur % 60);
                    $p1 = $h1 >= 12 ? 'PM' : 'AM';
                    $h1F = $h1 % 12 === 0 ? 12 : $h1 % 12;

                    $h2 = (int) floor($curEnd / 60);
                    $m2 = (int) ($curEnd % 60);
                    $p2 = $h2 >= 12 ? 'PM' : 'AM';
                    $h2F = $h2 % 12 === 0 ? 12 : $h2 % 12;

                    $slots[] = sprintf('%02d:%02d %s – %02d:%02d %s', $h1F, $m1, $p1, $h2F, $m2, $p2);
                }
                if (!empty($slots)) {
                    return $slots;
                }
            }
        }

        return [$this->getFormattedShiftAttribute()];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
