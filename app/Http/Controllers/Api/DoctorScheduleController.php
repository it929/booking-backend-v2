<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        $schedules = DoctorSchedule::with('doctor.department')->orderBy('day_of_week')->orderBy('start_time')->get();
        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required',
            'day_of_week' => 'nullable|string',
            'duty_days' => 'nullable|array',
            'shift_time' => 'nullable|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'slot_duration_minutes' => 'nullable|integer|min:10',
            'status' => 'nullable|boolean',
            'recurrence_type' => 'nullable|string',
            'recurrence_weeks' => 'nullable|array',
        ]);

        $doctor = Doctor::where('id', $validated['doctor_id'])->orWhere('code', $validated['doctor_id'])->firstOrFail();

        $days = [];
        if (!empty($validated['duty_days'])) {
            $days = (array) $validated['duty_days'];
        } elseif (!empty($validated['day_of_week'])) {
            $days = [$validated['day_of_week']];
        } else {
            $days = ['Mon'];
        }

        $shiftTime = $validated['shift_time'] ?? '08:00 AM – 02:00 PM';
        $capacity = $validated['capacity'] ?? 15;
        $slotMinutes = $validated['slot_duration_minutes'] ?? 30;

        // Parse start and end time
        $startTime = '08:00:00';
        $endTime = '14:00:00';
        if (!empty($validated['start_time']) && !empty($validated['end_time'])) {
            $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
            $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');
        } else {
            $parts = preg_split('/\s*(?:–|-|—|to)\s*/iu', $shiftTime);
            if (count($parts) >= 2) {
                try {
                    $startTime = Carbon::parse(trim($parts[0]))->format('H:i:s');
                    $endTime = Carbon::parse(trim($parts[1]))->format('H:i:s');
                } catch (\Exception $e) {}
            }
        }

        $created = [];
        foreach ($days as $dayItem) {
            [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence(
                $dayItem,
                $validated['recurrence_type'] ?? null,
                $validated['recurrence_weeks'] ?? null
            );

            $nextId = (DoctorSchedule::max('id') ?? 0) + 1;
            $schedule = DoctorSchedule::create([
                'code' => 'sched-' . $nextId,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'recurrence_type' => $recType,
                'recurrence_weeks' => $recWeeks,
                'shift_name' => 'Consultation Clinic',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'capacity' => $capacity,
                'slot_duration_minutes' => $slotMinutes,
                'room' => '',
                'total_weekly_capacity' => $capacity,
                'status' => $validated['status'] ?? true,
            ]);
            $created[] = $schedule;
        }

        $last = end($created);
        return response()->json($last->load('doctor'), 201);
    }

    public function show($id): JsonResponse
    {
        $schedule = DoctorSchedule::with('doctor')->where('id', $id)->orWhere('code', $id)->firstOrFail();
        return response()->json($schedule);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $schedule = DoctorSchedule::where('id', $id)->orWhere('code', $id)->firstOrFail();

        $validated = $request->validate([
            'room' => 'nullable|string',
            'day_of_week' => 'nullable|string',
            'duty_days' => 'nullable|array',
            'recurrence_type' => 'nullable|string',
            'recurrence_weeks' => 'nullable|array',
            'shift_time' => 'nullable|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'nullable|boolean',
        ]);

        if (!empty($validated['duty_days'])) {
            $firstDay = is_array($validated['duty_days']) ? $validated['duty_days'][0] : $validated['duty_days'];
            [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence(
                $firstDay,
                $validated['recurrence_type'] ?? null,
                $validated['recurrence_weeks'] ?? null
            );
            $validated['day_of_week'] = $dayOfWeek;
            $validated['recurrence_type'] = $recType;
            $validated['recurrence_weeks'] = $recWeeks;
            unset($validated['duty_days']);
        }

        if (!empty($validated['shift_time'])) {
            $parts = preg_split('/\s*(?:–|-|—|to)\s*/iu', $validated['shift_time']);
            if (count($parts) >= 2) {
                try {
                    $validated['start_time'] = Carbon::parse(trim($parts[0]))->format('H:i:s');
                    $validated['end_time'] = Carbon::parse(trim($parts[1]))->format('H:i:s');
                } catch (\Exception $e) {}
            }
            unset($validated['shift_time']);
        }

        $schedule->update($validated);
        return response()->json($schedule);
    }

    public function destroy($id): JsonResponse
    {
        $schedule = DoctorSchedule::where('id', $id)->orWhere('code', $id)->firstOrFail();
        $schedule->delete();
        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
