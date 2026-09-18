<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::with(['department', 'schedules'])
            ->leftJoin('departments', 'doctors.department_id', '=', 'departments.id')
            ->select('doctors.*');

        if ($request->has('department_id')) {
            $query->where('doctors.department_id', $request->department_id);
        }

        if ($request->has('status')) {
            $query->where('doctors.status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('doctors.name', 'like', "%{$search}%")
                  ->orWhere('doctors.full_name', 'like', "%{$search}%")
                  ->orWhere('doctors.code', 'like', "%{$search}%")
                  ->orWhere('doctors.acronym', 'like', "%{$search}%")
                  ->orWhere('departments.name', 'like', "%{$search}%");
            });
        }

        $doctors = $query->orderBy('departments.name', 'asc')
            ->orderBy('doctors.name', 'asc')
            ->get();

        if (!$request->user('sanctum')) {
            $doctors->each->maskForPublic();
        }

        return response()->json($doctors);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'full_name' => 'nullable|string|max:200',
            'surname' => 'nullable|string|max:100',
            'middlename' => 'nullable|string|max:100',
            'lastname' => 'nullable|string|max:100',
            'acronym' => 'nullable|string|max:50',
            'code' => 'nullable|string|max:100|unique:doctors,code',
            'department_id' => 'nullable|exists:departments,id',
            'qualification' => 'nullable|string|max:250',
            'bio' => 'nullable|string',
            'image_url' => 'nullable|string',
            'accepted_patient_types' => 'nullable|array',
            'accepts_private' => 'nullable|boolean',
            'accepts_hmo' => 'nullable|boolean',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            // Schedule fields if creating doctor together with schedule
            'room' => 'nullable|string',
            'duty_days' => 'nullable|array',
            'day_configs' => 'nullable|array',
            'shift_time' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['code'])) {
            $nextId = (Doctor::max('id') ?? 0) + 1;
            $validated['code'] = 'doc-' . $nextId;
        }

        if (!isset($validated['consultation_fee'])) {
            $validated['consultation_fee'] = 0.00;
        }

        // Parse full name into surname, middlename, lastname according to hospital rules:
        // 3 names (e.g. "Olaoye Afeez Babatunde"): surname=Olaoye, middlename=Afeez, lastname=Babatunde
        // 2 names (e.g. "Olaoye Afeez"): surname=Olaoye, middlename=Afeez, lastname=null
        // 1 name (e.g. "Olaoye"): surname=Olaoye, middlename=null, lastname=null
        $nameToParse = !empty($validated['full_name']) ? $validated['full_name'] : $validated['name'];
        $parsed = $this->parseNameParts($nameToParse);

        $validated['surname'] = !empty($validated['surname']) ? $validated['surname'] : $parsed['surname'];
        $validated['middlename'] = array_key_exists('middlename', $validated) ? $validated['middlename'] : $parsed['middlename'];
        $validated['lastname'] = array_key_exists('lastname', $validated) ? $validated['lastname'] : $parsed['lastname'];

        if (!empty($validated['acronym'])) {
            $candidate = trim($validated['acronym']);
            if (preg_match('/\s/', $candidate) || preg_match('/^(dr|doctor|mr|mrs|ms|miss|prof)\.?/i', $candidate) || strlen($candidate) > 5) {
                $validated['acronym'] = $this->computeInitials($nameToParse);
            } else {
                $validated['acronym'] = strtoupper($candidate);
            }
        } else {
            $validated['acronym'] = $this->computeInitials($nameToParse);
        }

        $validated['name'] = Doctor::formatDoctorTitle($validated['name']);
        $validated['full_name'] = Doctor::formatDoctorTitle(!empty($validated['full_name']) ? $validated['full_name'] : $validated['name']);

        // Synchronize accepts_private & accepts_hmo flags and accepted_patient_types
        $hasPriv = isset($validated['accepts_private']) ? (bool) $validated['accepts_private'] : null;
        $hasHmo = isset($validated['accepts_hmo']) ? (bool) $validated['accepts_hmo'] : null;

        if (!empty($validated['accepted_patient_types']) && is_array($validated['accepted_patient_types'])) {
            $types = $validated['accepted_patient_types'];
            if ($hasPriv === null) {
                $hasPriv = false;
                foreach ($types as $t) {
                    if (is_string($t) && preg_match('/private|self-pay/i', $t)) $hasPriv = true;
                }
            }
            if ($hasHmo === null) {
                $hasHmo = false;
                foreach ($types as $t) {
                    if (is_string($t) && preg_match('/hmo/i', $t)) $hasHmo = true;
                }
            }
        }

        if ($hasPriv === null && $hasHmo === null) {
            $hasPriv = true;
            $hasHmo = true;
        } else {
            $hasPriv = (bool) $hasPriv;
            $hasHmo = (bool) $hasHmo;
        }

        if (!$hasPriv && !$hasHmo) {
            $hasPriv = true;
            $hasHmo = true;
        }

        $validated['accepts_private'] = $hasPriv;
        $validated['accepts_hmo'] = $hasHmo;
        unset($validated['accepted_patient_types']);

        $doctor = Doctor::create($validated);

        // If granular day_configs are passed, create each day with its specific hours
        if (!empty($validated['day_configs']) && is_array($validated['day_configs'])) {
            foreach ($validated['day_configs'] as $cfg) {
                $rawDay = $cfg['day'] ?? ($cfg['base_day'] ?? 'Mon');
                $shiftTime = $cfg['shift_time'] ?? '08:00 AM – 02:00 PM';
                $capacity = $cfg['capacity'] ?? ($validated['capacity'] ?? 15);
                $weeks = !empty($cfg['weeks']) ? (array) $cfg['weeks'] : (!empty($cfg['recurrence_weeks']) ? (array) $cfg['recurrence_weeks'] : null);
                $presetType = !empty($cfg['recurrence_type']) ? $cfg['recurrence_type'] : null;

                [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence($rawDay, $presetType, $weeks);
                
                $startTime = !empty($cfg['start_time']) ? Carbon::parse($cfg['start_time'])->format('H:i:s') : null;
                $endTime = !empty($cfg['end_time']) ? Carbon::parse($cfg['end_time'])->format('H:i:s') : null;
                if (!$startTime || !$endTime) {
                    [$startTime, $endTime] = $this->parseShiftTimes($shiftTime);
                }

                $schedId = (DoctorSchedule::max('id') ?? 0) + 1;
                DoctorSchedule::create([
                    'code' => 'sched-' . $schedId,
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $dayOfWeek,
                    'recurrence_type' => $recType,
                    'recurrence_weeks' => $recWeeks,
                    'shift_name' => $cfg['shift_name'] ?? 'Consultation Clinic',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity' => $capacity,
                    'slot_duration_minutes' => 30,
                    'room' => $validated['room'] ?? '',
                    'total_weekly_capacity' => $capacity,
                    'status' => true,
                ]);
            }
        } elseif (!empty($validated['duty_days']) || !empty($validated['shift_time'])) {
            $days = !empty($validated['duty_days']) ? (array) $validated['duty_days'] : ['Mon', 'Wed', 'Fri'];
            $shiftTime = $validated['shift_time'] ?? '08:00 AM – 02:00 PM';
            $capacity = $validated['capacity'] ?? 15;
            [$startTime, $endTime] = $this->parseShiftTimes($shiftTime);

            foreach ($days as $dayItem) {
                [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence($dayItem);

                $schedId = (DoctorSchedule::max('id') ?? 0) + 1;
                DoctorSchedule::create([
                    'code' => 'sched-' . $schedId,
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $dayOfWeek,
                    'recurrence_type' => $recType,
                    'recurrence_weeks' => $recWeeks,
                    'shift_name' => 'Consultation Clinic',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity' => $capacity,
                    'slot_duration_minutes' => 30,
                    'room' => $validated['room'] ?? '',
                    'total_weekly_capacity' => $capacity,
                    'status' => true,
                ]);
            }
        }

        return response()->json($doctor->load(['department', 'schedules']), 201);
    }

    /**
     * Parse full name into surname, middlename, and lastname components.
     * Handles salutations/titles (Dr., Prof., etc.).
     */
    private function parseNameParts(?string $fullName): array
    {
        if (empty($fullName)) {
            return [
                'surname' => null,
                'middlename' => null,
                'lastname' => null,
            ];
        }

        $clean = preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $fullName);
        $parts = array_values(array_filter(preg_split('/[\s\-_.]+/', trim($clean))));

        if (count($parts) === 0) {
            $parts = array_values(array_filter(preg_split('/[\s\-_.]+/', trim($fullName))));
        }

        if (count($parts) === 0) {
            return [
                'surname' => null,
                'middlename' => null,
                'lastname' => null,
            ];
        }

        if (count($parts) === 1) {
            return [
                'surname' => $parts[0],
                'middlename' => null,
                'lastname' => null,
            ];
        }

        if (count($parts) === 2) {
            return [
                'surname' => $parts[0],
                'middlename' => $parts[1],
                'lastname' => null,
            ];
        }

        return [
            'surname' => $parts[0],
            'middlename' => $parts[1],
            'lastname' => implode(' ', array_slice($parts, 2)),
        ];
    }

    private function computeInitials(?string $fullName): string
    {
        $clean = trim(preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $fullName ?? ''));
        $parts = preg_split('/[\s\-_.]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($parts as $p) {
            if (preg_match('/[a-zA-Z]/', $p, $m)) {
                $initials .= strtoupper($m[0]);
            }
        }
        return !empty($initials) ? $initials : 'DOC';
    }

    private function parseShiftTimes(?string $shiftTime): array
    {
        $default = ['08:00:00', '14:00:00'];
        if (empty($shiftTime)) {
            return $default;
        }

        $parts = preg_split('/\s*(?:–|-|—|to)\s*/iu', $shiftTime);
        if (count($parts) >= 2) {
            try {
                $start = Carbon::parse(trim($parts[0]))->format('H:i:s');
                $end = Carbon::parse(trim($parts[1]))->format('H:i:s');
                return [$start, $end];
            } catch (\Exception $e) {
                return $default;
            }
        }
        return $default;
    }

    public function show(Request $request, $id): JsonResponse
    {
        $doctor = Doctor::with(['department', 'schedules'])->where('id', $id)->orWhere('code', $id)->firstOrFail();
        if (!$request->user('sanctum')) {
            $doctor->maskForPublic();
        }
        return response()->json($doctor);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $doctor = Doctor::where('id', $id)->orWhere('code', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'full_name' => 'nullable|string|max:200',
            'surname' => 'nullable|string|max:100',
            'middlename' => 'nullable|string|max:100',
            'lastname' => 'nullable|string|max:100',
            'acronym' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'qualification' => 'nullable|string|max:250',
            'bio' => 'nullable|string',
            'image_url' => 'nullable|string',
            'accepted_patient_types' => 'nullable|array',
            'accepts_private' => 'nullable|boolean',
            'accepts_hmo' => 'nullable|boolean',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'room' => 'nullable|string',
            'duty_days' => 'nullable|array',
            'day_configs' => 'nullable|array',
            'shift_time' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['name']) || isset($validated['full_name'])) {
            $nameToParse = !empty($validated['full_name']) ? $validated['full_name'] : ($validated['name'] ?? $doctor->name);
            $parsed = $this->parseNameParts($nameToParse);
            $validated['surname'] = !empty($validated['surname']) ? $validated['surname'] : $parsed['surname'];
            $validated['middlename'] = array_key_exists('middlename', $validated) ? $validated['middlename'] : $parsed['middlename'];
            $validated['lastname'] = array_key_exists('lastname', $validated) ? $validated['lastname'] : $parsed['lastname'];

            if (!empty($validated['acronym'])) {
                $candidate = trim($validated['acronym']);
                if (preg_match('/\s/', $candidate) || preg_match('/^(dr|doctor|mr|mrs|ms|miss|prof)\.?/i', $candidate) || strlen($candidate) > 5) {
                    $validated['acronym'] = $this->computeInitials($nameToParse);
                } else {
                    $validated['acronym'] = strtoupper($candidate);
                }
            } elseif (empty($doctor->acronym) || strlen($doctor->acronym) > 5 || preg_match('/\s/', $doctor->acronym) || preg_match('/^(dr|doctor|mr|mrs|ms|miss|prof)\.?/i', $doctor->acronym)) {
                $validated['acronym'] = $this->computeInitials($nameToParse);
            }

            if (isset($validated['name'])) {
                $validated['name'] = Doctor::formatDoctorTitle($validated['name']);
            }
            if (isset($validated['full_name'])) {
                $validated['full_name'] = Doctor::formatDoctorTitle($validated['full_name']);
            }
        }

        // Synchronize accepts_private & accepts_hmo flags and accepted_patient_types
        if (array_key_exists('accepts_private', $validated) || array_key_exists('accepts_hmo', $validated) || array_key_exists('accepted_patient_types', $validated)) {
            $hasPriv = array_key_exists('accepts_private', $validated)
                ? (bool) $validated['accepts_private']
                : ($doctor->accepts_private ?? true);

            $hasHmo = array_key_exists('accepts_hmo', $validated)
                ? (bool) $validated['accepts_hmo']
                : ($doctor->accepts_hmo ?? true);

            if (array_key_exists('accepted_patient_types', $validated) && !array_key_exists('accepts_private', $validated) && !array_key_exists('accepts_hmo', $validated)) {
                $types = (array) ($validated['accepted_patient_types'] ?? []);
                $hasPriv = false;
                $hasHmo = false;
                foreach ($types as $t) {
                    if (is_string($t) && preg_match('/private|self-pay/i', $t)) $hasPriv = true;
                    if (is_string($t) && preg_match('/hmo/i', $t)) $hasHmo = true;
                }
            }

            if (!$hasPriv && !$hasHmo) {
                $hasPriv = true;
                $hasHmo = true;
            }

            $validated['accepts_private'] = $hasPriv;
            $validated['accepts_hmo'] = $hasHmo;
            unset($validated['accepted_patient_types']);
        }

        $doctor->update($validated);

        // Synchronize duty day schedules if day_configs supplied
        if (!empty($validated['day_configs']) && is_array($validated['day_configs'])) {
            $doctor->schedules()->delete();
            foreach ($validated['day_configs'] as $cfg) {
                $rawDay = $cfg['day'] ?? ($cfg['base_day'] ?? 'Mon');
                $shiftTime = $cfg['shift_time'] ?? '08:00 AM – 02:00 PM';
                $capacity = $cfg['capacity'] ?? $validated['capacity'] ?? 15;
                $weeks = !empty($cfg['weeks']) ? (array) $cfg['weeks'] : (!empty($cfg['recurrence_weeks']) ? (array) $cfg['recurrence_weeks'] : null);
                $presetType = !empty($cfg['recurrence_type']) ? $cfg['recurrence_type'] : null;

                [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence($rawDay, $presetType, $weeks);

                $startTime = !empty($cfg['start_time']) ? Carbon::parse($cfg['start_time'])->format('H:i:s') : null;
                $endTime = !empty($cfg['end_time']) ? Carbon::parse($cfg['end_time'])->format('H:i:s') : null;
                if (!$startTime || !$endTime) {
                    [$startTime, $endTime] = $this->parseShiftTimes($shiftTime);
                }

                $schedId = (DoctorSchedule::max('id') ?? 0) + 1;
                DoctorSchedule::create([
                    'code' => 'sched-' . $schedId,
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $dayOfWeek,
                    'recurrence_type' => $recType,
                    'recurrence_weeks' => $recWeeks,
                    'shift_name' => $cfg['shift_name'] ?? 'Consultation Clinic',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity' => $capacity,
                    'slot_duration_minutes' => 30,
                    'room' => $validated['room'] ?? '',
                    'total_weekly_capacity' => $capacity,
                    'status' => true,
                ]);
            }
        } elseif (isset($validated['duty_days']) || isset($validated['shift_time']) || isset($validated['room']) || isset($validated['capacity'])) {
            if (!empty($validated['duty_days']) && is_array($validated['duty_days'])) {
                $doctor->schedules()->delete();
                $shiftTime = $validated['shift_time'] ?? '08:00 AM – 02:00 PM';
                $capacity = $validated['capacity'] ?? 15;
                [$startTime, $endTime] = $this->parseShiftTimes($shiftTime);

                foreach ($validated['duty_days'] as $dayItem) {
                    [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence($dayItem);
                    $schedId = (DoctorSchedule::max('id') ?? 0) + 1;
                    DoctorSchedule::create([
                        'code' => 'sched-' . $schedId,
                        'doctor_id' => $doctor->id,
                        'day_of_week' => $dayOfWeek,
                        'recurrence_type' => $recType,
                        'recurrence_weeks' => $recWeeks,
                        'shift_name' => 'Consultation Clinic',
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'capacity' => $capacity,
                        'slot_duration_minutes' => 30,
                        'room' => $validated['room'] ?? '',
                        'total_weekly_capacity' => $capacity,
                        'status' => true,
                    ]);
                }
            } else {
                $schedule = $doctor->schedules()->first();
                if ($schedule) {
                    $updateData = array_filter([
                        'room' => $validated['room'] ?? null,
                        'capacity' => $validated['capacity'] ?? null,
                    ]);
                    if (!empty($validated['shift_time'])) {
                        [$sTime, $eTime] = $this->parseShiftTimes($validated['shift_time']);
                        $updateData['start_time'] = $sTime;
                        $updateData['end_time'] = $eTime;
                    }
                    $schedule->update($updateData);
                }
            }
        }

        return response()->json($doctor->fresh(['department', 'schedules']));
    }

    public function destroy($id): JsonResponse
    {
        $doctor = Doctor::where('id', $id)->orWhere('code', $id)->firstOrFail();
        $doctor->delete();
        return response()->json(['message' => 'Doctor deleted successfully']);
    }
}
