<?php

namespace App\Http\Controllers\Api;

use App\Enums\BillingType;
use App\Enums\BookingStatus;
use App\Enums\HmoAuthStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\HmoCompany;
use App\Models\Patient;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['doctor.department', 'doctor.schedules', 'department', 'hmoCompany', 'patient', 'payments']);

        // Active status filtering (default true unless explicitly querying disabled)
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_active', true);
        }

        // Date filter
        if ($request->has('date') && !empty($request->date)) {
            $query->whereDate('appointment_date', $request->date);
        }

        // Doctor filter
        if ($request->has('doctor_id') && !empty($request->doctor_id)) {
            $docParam = $request->doctor_id;
            $query->whereHas('doctor', function ($q) use ($docParam) {
                $q->where('id', $docParam)->orWhere('code', $docParam);
            });
        }

        // Department filter
        if ($request->has('department_id') && !empty($request->department_id)) {
            $deptParam = $request->department_id;
            $query->whereHas('department', function ($q) use ($deptParam) {
                $q->where('id', $deptParam)->orWhere('code', $deptParam);
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Patient category / type filter (Private vs HMO)
        if ($request->has('patient_type') && !empty($request->patient_type) && strtolower($request->patient_type) !== 'all') {
            $query->patientType($request->patient_type);
        }

        // Payment type filter
        if ($request->has('payment_type') && !empty($request->payment_type)) {
            $query->where('payment_type', $request->payment_type);
        }

        // Payment status filter
        if ($request->has('payment_status') && !empty($request->payment_status)) {
            $query->where('payment_status', $request->payment_status);
        }

        // HMO status filter
        if ($request->has('hmo_status') && !empty($request->hmo_status)) {
            $query->where('hmo_status', $request->hmo_status);
        }

        // General search
        if ($request->has('search') && !empty($request->search)) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('patient_name', 'like', "%{$search}%")
                  ->orWhere('patient_phone', 'like', "%{$search}%")
                  ->orWhere('patient_email', 'like', "%{$search}%")
                  ->orWhere('hmo_policy_code', 'like', "%{$search}%")
                  ->orWhere('hmo_auth_code', 'like', "%{$search}%")
                  ->orWhere('invoice_ref', 'like', "%{$search}%");
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();
        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required',
            'date' => 'required',
            'time' => 'required',
            'patient_name' => 'required|string|max:200',
            'patient_phone' => 'required|string|max:50',
            'patient_email' => 'nullable|email|max:150',
            'reason' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'hmo_id' => 'nullable',
            'hmo_name' => 'nullable|string',
            'hmo_policy_code' => 'nullable|string',
            'referral_doc_name' => 'nullable|string',
            'referral_doc_data' => 'nullable|string',
            'referral_doc_text' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Resolve Doctor
            $docParam = $validated['doctor_id'];
            $doctor = Doctor::with(['department', 'schedules'])
                ->where('id', $docParam)
                ->orWhere('code', $docParam)
                ->first();

            if (!$doctor) {
                return response()->json(['error' => 'Doctor not found', 'status' => 404], 404);
            }

            // 2. Parse Date
            try {
                $appDate = Carbon::parse($validated['date'])->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid appointment date format', 'status' => 422], 422);
            }

            // 3. Verify Doctor Capacity & Schedule
            $parsedAppDate = Carbon::parse($appDate);
            $dayOfWeekShort = $parsedAppDate->format('D');

            $matchingSched = $doctor->schedules()
                ->activeOnDay($dayOfWeekShort)
                ->get()
                ->first(function ($s) use ($parsedAppDate) {
                    return $s->isOnDutyOnDate($parsedAppDate);
                });

            if (!$matchingSched) {
                // Fallback for any legacy records
                $matchingSched = $doctor->schedules()
                    ->where('status', true)
                    ->get()
                    ->first(function ($s) use ($parsedAppDate) {
                        return $s->isOnDutyOnDate($parsedAppDate);
                    });
            }

            if (!$matchingSched) {
                return response()->json([
                    'error' => 'Doctor not on duty',
                    'detail' => "Doctor {$doctor->initial_name} is not scheduled for consultations on {$appDate} ({$dayOfWeekShort}). Please select an active duty date from the calendar.",
                    'status' => 422,
                ], 422);
            }

            // Check 10-minute cut-off for today's clinic commencement
            if ($parsedAppDate->isToday() && $matchingSched->isBookingClosedForDate($parsedAppDate)) {
                $cutoff = $matchingSched->getCutoffTimeForToday();
                $cutoffStr = $cutoff ? $cutoff->format('h:i A') : '10 minutes prior to clinic start';
                return response()->json([
                    'error' => 'Clinic booking closed for today',
                    'detail' => "Bookings for Doctor {$doctor->initial_name}'s clinic today closed at {$cutoffStr} (10 minutes prior to clinic commencement). Please select a future clinic date.",
                    'status' => 422,
                ], 422);
            }

            $capacity = $matchingSched->capacity ?: 15;

            // Concurrency Protection: Lock rows for this doctor & appointment date to serialize concurrent booking attempts
            $existingBookingsCount = Booking::where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $appDate)
                ->where('is_active', true)
                ->whereNotIn('status', ['Cancelled', 'Rejected', 'Deleted'])
                ->lockForUpdate()
                ->count();

            if ($existingBookingsCount >= $capacity) {
                return response()->json([
                    'error' => 'Daily capacity reached',
                    'detail' => "Doctor {$doctor->initial_name} is fully booked on {$appDate} ({$existingBookingsCount}/{$capacity} slots taken). Please select another date.",
                    'status' => 409,
                ], 409);
            }

            // 4. Resolve or Create Patient Record
            $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['patient_phone']);
            $patient = Patient::where('phone', $cleanPhone)
                ->orWhere(function ($q) use ($validated) {
                    if (!empty($validated['patient_email'])) {
                        $q->where('email', strtolower(trim($validated['patient_email'])));
                    }
                })
                ->first();

            if (!$patient) {
                $mrnSeq = (Patient::max('id') ?? 0) + 1;
                $mrn = 'ISL-PAT-' . str_pad($mrnSeq, 5, '0', STR_PAD_LEFT);
                $patient = Patient::create([
                    'mrn' => $mrn,
                    'name' => $validated['patient_name'],
                    'phone' => $cleanPhone,
                    'email' => !empty($validated['patient_email']) ? strtolower(trim($validated['patient_email'])) : null,
                ]);
            }

            // 5. Resolve HMO Company if applicable
            $hmoId = null;
            $rawPaymentType = $validated['payment_type'] ?? 'Private Self-Pay';
            $isHmo = (stripos($rawPaymentType, 'hmo') !== false) || !empty($validated['hmo_id']) || (!empty($validated['hmo_name']) && $validated['hmo_name'] !== 'N/A');
            $paymentType = $isHmo ? BillingType::HMO->value : BillingType::PRIVATE->value;
            $hmoStatus = $isHmo ? HmoAuthStatus::PENDING->value : null;
            $paymentStatus = $isHmo ? PaymentStatus::COVERED_BY_HMO->value : PaymentStatus::PENDING->value;

            if ($isHmo) {
                if (!empty($validated['hmo_id'])) {
                    $hmo = HmoCompany::where('id', $validated['hmo_id'])->orWhere('code', $validated['hmo_id'])->first();
                    if ($hmo) $hmoId = $hmo->id;
                } elseif (!empty($validated['hmo_name']) && $validated['hmo_name'] !== 'N/A') {
                    $hmo = HmoCompany::where('name', 'like', "%{$validated['hmo_name']}%")->first();
                    if ($hmo) $hmoId = $hmo->id;
                }
            }

            // 6. Generate Unique Reference Code
            do {
                $refCode = 'ISL-' . rand(10000, 99999);
            } while (Booking::where('reference_code', $refCode)->exists());

            // 7. Create Booking
            $booking = Booking::create([
                'reference_code' => $refCode,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'department_id' => $doctor->department_id,
                'hmo_company_id' => $hmoId,
                'appointment_date' => $appDate,
                'appointment_time' => $validated['time'],
                'patient_name' => $validated['patient_name'],
                'patient_phone' => $cleanPhone,
                'patient_email' => $validated['patient_email'] ?? null,
                'reason' => $validated['reason'] ?? '',
                'payment_type' => $paymentType,
                'hmo_policy_code' => $validated['hmo_policy_code'] ?? '',
                'hmo_auth_code' => '',
                'hmo_status' => $hmoStatus,
                'referral_doc_name' => $validated['referral_doc_name'] ?? '',
                'referral_doc_data' => $validated['referral_doc_data'] ?? '',
                'referral_doc_text' => $validated['referral_doc_text'] ?? '',
                'payment_status' => $paymentStatus,
                'payment_method' => 'POS / Cash',
                'status' => BookingStatus::CONFIRMED->value,
                'is_active' => true,
            ]);

            // 8. Log initial status
            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'user_id' => $request->user() ? $request->user()->id : null,
                'from_status' => 'None',
                'to_status' => BookingStatus::CONFIRMED->value,
                'note' => 'Appointment booked successfully via portal',
            ]);

            $booking->load(['doctor.department', 'department', 'hmoCompany', 'patient']);
            if (!$request->user('sanctum')) {
                $booking->doctor?->maskForPublic();
                if ($booking->doctor) {
                    $booking->doctor_name = $booking->doctor->initial_name;
                }
            }

            return response()->json($booking, 201);
        });
    }

    public function show(Request $request, $id): JsonResponse
    {
        $booking = Booking::with(['doctor.department', 'department', 'hmoCompany', 'patient', 'statusLogs.user', 'payments'])
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        if (!$request->user('sanctum')) {
            $booking->doctor?->maskForPublic();
            if ($booking->doctor) {
                $booking->doctor_name = $booking->doctor->initial_name;
            }
        }

        return response()->json($booking);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $validated = $request->validate([
            'patient_name' => 'sometimes|string|max:200',
            'patient_phone' => 'sometimes|string|max:50',
            'patient_email' => 'nullable|email|max:150',
            'appointment_date' => 'sometimes|date',
            'appointment_time' => 'sometimes|string',
            'reason' => 'nullable|string',
            'status' => 'sometimes|string',
            'payment_status' => 'sometimes|string',
            'payment_method' => 'sometimes|string',
            'hmo_status' => 'sometimes|nullable|string',
            'hmo_auth_code' => 'nullable|string',
            'hmo_policy_code' => 'nullable|string',
        ]);

        $oldStatusStr = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;
        $booking->update($validated);

        if (!empty($validated['status']) && $validated['status'] !== $oldStatusStr) {
            $extra = [];
            if ($validated['status'] === BookingStatus::CHECKED_IN->value && !$booking->checked_in_at) {
                $extra['checked_in_at'] = now();
            } elseif ($validated['status'] === BookingStatus::COMPLETED->value && !$booking->completed_at) {
                $extra['completed_at'] = now();
            }
            if (!empty($extra)) {
                $booking->update($extra);
            }

            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'user_id' => $request->user() ? $request->user()->id : null,
                'from_status' => $oldStatusStr,
                'to_status' => $validated['status'],
                'note' => $request->input('note', 'Booking status updated to ' . $validated['status']),
            ]);
        }

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking->fresh(['doctor.department', 'department', 'hmoCompany', 'patient']),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();
        $reason = $request->input('reason', 'Cancelled by user / staff');
        $oldStatusStr = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;

        $booking->update([
            'is_active' => false,
            'status' => BookingStatus::CANCELLED->value,
            'delete_reason' => $reason,
        ]);

        BookingStatusLog::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'from_status' => $oldStatusStr,
            'to_status' => BookingStatus::CANCELLED->value,
            'note' => 'Booking cancelled. Reason: ' . $reason,
        ]);

        return response()->json(['message' => 'Booking cancelled successfully']);
    }

    public function lookup(Request $request): JsonResponse
    {
        $ref = trim($request->input('ref_code') ?? $request->input('reference') ?? $request->input('reference_code') ?? '');
        $phone = trim($request->input('phone') ?? $request->input('patient_phone') ?? '');

        if (empty($ref)) {
            return response()->json(['error' => 'Reference code is required', 'status' => 400], 400);
        }

        $query = Booking::with(['doctor.department', 'doctor.schedules', 'department', 'hmoCompany', 'patient', 'statusLogs', 'payments'])
            ->where('reference_code', $ref);

        if (!empty($phone)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            $query->where(function ($q) use ($cleanPhone) {
                $q->where('patient_phone', 'like', "%{$cleanPhone}%");
            });
        }

        $booking = $query->first();

        if (!$booking) {
            return response()->json([
                'error' => 'Booking not found',
                'detail' => 'No active appointment matching this reference code and phone was found.',
                'status' => 404,
            ], 404);
        }

        if (!$request->user('sanctum')) {
            $booking->doctor?->maskForPublic();
            if ($booking->doctor) {
                $booking->doctor_name = $booking->doctor->initial_name;
            }
        }

        return response()->json($booking);
    }

    public function availability(Request $request): JsonResponse
    {
        $docParam = $request->input('doctor_id');
        $dateParam = $request->input('date');

        if (empty($docParam) || empty($dateParam)) {
            return response()->json(['error' => 'Doctor ID and Date are required', 'status' => 400], 400);
        }

        $doctor = Doctor::with('schedules')->where('id', $docParam)->orWhere('code', $docParam)->first();

        if (!$doctor) {
            return response()->json(['error' => 'Doctor not found', 'status' => 404], 404);
        }

        $parsedDate = Carbon::parse($dateParam);
        $dayOfWeekShort = $parsedDate->format('D'); // e.g. Mon, Tue, Wed, Sat, Sun

        // 1. Fast indexed lookup by (doctor_id, day_of_week, status)
        $matchingSchedule = $doctor->schedules()
            ->activeOnDay($dayOfWeekShort)
            ->get()
            ->first(function ($s) use ($parsedDate) {
                return $s->isOnDutyOnDate($parsedDate);
            });

        // Fallback for any legacy records
        if (!$matchingSchedule) {
            $matchingSchedule = $doctor->schedules()
                ->where('status', true)
                ->get()
                ->first(function ($s) use ($parsedDate) {
                    return $s->isOnDutyOnDate($parsedDate);
                });
        }

        $sched = $matchingSchedule;
        $isDoctorOnDuty = ($sched !== null);
        $capacity = $isDoctorOnDuty ? ($sched->capacity ?: 15) : 0;

        $bookedCount = Booking::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $parsedDate->format('Y-m-d'))
            ->where('is_active', true)
            ->whereNotIn('status', ['Cancelled', 'Rejected', 'Deleted'])
            ->count();

        $remainingSlots = $isDoctorOnDuty ? max(0, $capacity - $bookedCount) : 0;

        $isBookingClosed = false;
        $closedReason = null;
        if ($isDoctorOnDuty && $sched && $parsedDate->isToday()) {
            if ($sched->isBookingClosedForDate($parsedDate)) {
                $isBookingClosed = true;
                $cutoff = $sched->getCutoffTimeForToday();
                $cutoffStr = $cutoff ? $cutoff->format('h:i A') : '10 minutes prior to clinic start';
                $closedReason = "Bookings for Doctor {$doctor->initial_name}'s clinic today closed at {$cutoffStr} (10 minutes prior to clinic commencement).";
            }
        }

        // Slots specific to this day's shift
        $slots = $isDoctorOnDuty && $sched && !empty($sched->generated_slots) 
            ? $sched->generated_slots 
            : [];

        $formattedShift = $isDoctorOnDuty && $sched ? $sched->formatted_shift : 'Off Duty';

        return response()->json([
            'doctor_id' => $doctor->id,
            'doctor_code' => $doctor->code,
            'doctor_name' => $request->user('sanctum') ? ($doctor->full_name ?: $doctor->name) : $doctor->initial_name,
            'date' => $parsedDate->format('Y-m-d'),
            'day_of_week' => $dayOfWeekShort,
            'on_duty' => $isDoctorOnDuty,
            'daily_capacity' => $capacity,
            'booked_count' => $bookedCount,
            'remaining_slots' => $isBookingClosed ? 0 : $remainingSlots,
            'is_available' => $isDoctorOnDuty && ($remainingSlots > 0) && !$isBookingClosed,
            'is_fully_booked' => $isDoctorOnDuty && ($capacity > 0) && ($bookedCount >= $capacity),
            'is_booking_closed' => $isBookingClosed,
            'closed_reason' => $closedReason,
            'formatted_shift' => $formattedShift,
            'time_slots' => $slots,
            'room' => $sched ? ($sched->room ?? '') : '',
        ]);
    }

    public function checkIn(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $booking->update([
            'status' => 'Checked In',
            'checked_in_at' => now(),
        ]);

        BookingStatusLog::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'from_status' => 'Confirmed',
            'to_status' => 'Checked In',
            'note' => 'Patient verified and checked in at Front Desk',
        ]);

        return response()->json([
            'message' => 'Patient successfully checked in',
            'booking' => $booking->fresh(['doctor', 'patient', 'hmoCompany']),
        ]);
    }

    public function approveHmo(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $authCode = trim($request->input('auth_code') ?? $request->input('hmo_auth_code') ?? '');
        if (empty($authCode)) {
            return response()->json(['error' => 'Authorization code is required', 'status' => 422], 422);
        }

        $oldStatusStr = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;
        $booking->update([
            'hmo_auth_code' => $authCode,
            'hmo_status' => HmoAuthStatus::APPROVED->value,
            'payment_status' => PaymentStatus::COVERED_BY_HMO->value,
            'status' => 'HMO Approved',
        ]);

        BookingStatusLog::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'from_status' => $oldStatusStr,
            'to_status' => 'HMO Approved',
            'note' => 'HMO Pre-authorization granted with Code: ' . $authCode . '. Status changed to HMO Approved.',
        ]);

        return response()->json([
            'message' => 'HMO claim approved successfully',
            'booking' => $booking->fresh(['doctor', 'patient', 'hmoCompany']),
        ]);
    }

    public function payCashdesk(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $amount = (float) ($request->input('amount') ?? ($booking->doctor?->consultation_fee ?: 0));
        $method = $request->input('payment_method', 'Cashdesk Clearance');
        $invRef = 'CLR-' . strtoupper(Str::random(6));

        Payment::create([
            'booking_id' => $booking->id,
            'cashier_user_id' => $request->user() ? $request->user()->id : null,
            'invoice_number' => $invRef,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
            'notes' => 'Patient cleared for payment at cashdesk',
        ]);

        $oldStatusStr = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;
        $booking->update([
            'payment_status' => PaymentStatus::PAID->value,
            'payment_method' => $method,
            'invoice_ref' => $invRef,
            'status' => 'Payment Approved',
        ]);

        BookingStatusLog::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'from_status' => $oldStatusStr,
            'to_status' => 'Payment Approved',
            'note' => "Patient cleared for payment via {$method}. Ref: {$invRef}. Status changed to Payment Approved.",
        ]);

        return response()->json([
            'message' => 'Patient cleared for payment successfully',
            'invoice_ref' => $invRef,
            'booking' => $booking->fresh(['doctor', 'patient', 'payments']),
        ]);
    }

    public function rerouteToCashdesk(Request $request, $id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $booking->update([
            'payment_type' => BillingType::PRIVATE->value,
            'hmo_status' => HmoAuthStatus::REROUTED->value,
            'payment_status' => PaymentStatus::PENDING->value,
        ]);

        BookingStatusLog::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'from_status' => 'HMO Clearance',
            'to_status' => BillingType::PRIVATE->value,
            'note' => 'HMO declined or unverified. Patient rerouted to Cashdesk for private payment.',
        ]);

        return response()->json([
            'message' => 'Booking rerouted to Cashdesk billing',
            'booking' => $booking->fresh(),
        ]);
    }

    public function reschedule(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $booking = Booking::with(['doctor.schedules', 'department', 'hmoCompany', 'patient'])
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        // 1. Verify eligibility
        if (!$booking->is_active) {
            return response()->json([
                'error' => 'Booking is inactive',
                'detail' => 'This booking record is inactive or disabled and cannot be rescheduled.',
                'status' => 422,
            ], 422);
        }

        $terminalStatuses = ['Completed', 'Cancelled', 'Rejected', 'Deleted'];
        $currentStatus = $booking->status instanceof \BackedEnum ? $booking->status->value : (string) $booking->status;

        if (in_array($currentStatus, $terminalStatuses, true)) {
            return response()->json([
                'error' => 'Cannot reschedule appointment',
                'detail' => "This appointment is marked as {$currentStatus} and cannot be rescheduled.",
                'status' => 422,
            ], 422);
        }

        if (in_array($currentStatus, ['Checked In', 'In Consultation', 'Consulting'], true)) {
            return response()->json([
                'error' => 'Patient already checked in',
                'detail' => 'Patient has already arrived and checked in for clinical triage. Please see the reception desk for assistance.',
                'status' => 422,
            ], 422);
        }

        // 2. Validate Doctor & Schedule on New Date
        $doctor = $booking->doctor;
        if (!$doctor) {
            return response()->json(['error' => 'Doctor record not found', 'status' => 404], 404);
        }

        $newDateStr = $validated['date'];
        $newTimeStr = $validated['time'];
        $parsedNewDate = Carbon::parse($newDateStr);

        $currentBookingDateStr = $booking->appointment_date ? Carbon::parse($booking->appointment_date)->format('Y-m-d') : null;
        if ($currentBookingDateStr && $newDateStr === $currentBookingDateStr) {
            return response()->json([
                'error' => 'Same appointment date selected',
                'detail' => 'The selected date is already your current appointment date. Please choose a different date to reschedule.',
                'status' => 422,
            ], 422);
        }

        if ($parsedNewDate->startOfDay()->isPast() && !$parsedNewDate->isToday()) {
            return response()->json([
                'error' => 'Invalid appointment date',
                'detail' => 'Rescheduled appointment date must be today or a future date.',
                'status' => 422,
            ], 422);
        }

        $dayOfWeekShort = $parsedNewDate->format('D'); // e.g. Mon, Tue, Wed

        // Match schedule
        $matchingSched = $doctor->schedules()
            ->activeOnDay($dayOfWeekShort)
            ->get()
            ->first(function ($s) use ($parsedNewDate) {
                return $s->isOnDutyOnDate($parsedNewDate);
            });

        if (!$matchingSched) {
            $matchingSched = $doctor->schedules()
                ->where('status', true)
                ->get()
                ->first(function ($s) use ($parsedNewDate) {
                    return $s->isOnDutyOnDate($parsedNewDate);
                });
        }

        if (!$matchingSched) {
            return response()->json([
                'error' => 'Doctor not on duty',
                'detail' => "Doctor {$doctor->initial_name} is not on duty on {$newDateStr} ({$dayOfWeekShort}). Please choose an active duty day from the schedule.",
                'status' => 422,
            ], 422);
        }

        // Check 10-minute cut-off for today's clinic commencement
        if ($parsedNewDate->isToday() && $matchingSched->isBookingClosedForDate($parsedNewDate)) {
            $cutoff = $matchingSched->getCutoffTimeForToday();
            $cutoffStr = $cutoff ? $cutoff->format('h:i A') : '10 minutes prior to clinic start';
            return response()->json([
                'error' => 'Clinic booking closed for today',
                'detail' => "Bookings for Doctor {$doctor->initial_name}'s clinic today closed at {$cutoffStr} (10 minutes prior to clinic commencement). Please select a future clinic date.",
                'status' => 422,
            ], 422);
        }

        $capacity = $matchingSched->capacity ?: 15;

        // Concurrency lock & capacity check on the new date
        return DB::transaction(function () use ($booking, $doctor, $newDateStr, $newTimeStr, $validated, $capacity, $currentStatus, $request) {
            $existingBookingsCount = Booking::where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $newDateStr)
                ->where('is_active', true)
                ->where('id', '!=', $booking->id)
                ->whereNotIn('status', ['Cancelled', 'Rejected', 'Deleted'])
                ->lockForUpdate()
                ->count();

            if ($existingBookingsCount >= $capacity) {
                return response()->json([
                    'error' => 'Clinic capacity reached',
                    'detail' => "Doctor {$doctor->initial_name} is fully booked on {$newDateStr} ({$existingBookingsCount}/{$capacity} slots reserved). Please select another date.",
                    'status' => 409,
                ], 409);
            }

            $oldDate = $booking->appointment_date ? Carbon::parse($booking->appointment_date)->format('Y-m-d') : 'Unknown Date';
            $oldTime = $booking->appointment_time ?: 'Unknown Time';
            $reasonText = trim($validated['reason'] ?? '');

            // Update booking date & time
            $bookingNote = $booking->reason;
            if (!empty($reasonText)) {
                $bookingNote = $bookingNote ? "{$bookingNote} | Rescheduled: {$reasonText}" : "Rescheduled: {$reasonText}";
            }

            $booking->update([
                'appointment_date' => $newDateStr,
                'appointment_time' => $newTimeStr,
                'reason' => $bookingNote,
            ]);

            // Create Audit Status Log
            $logNote = "Appointment rescheduled from {$oldDate} at {$oldTime} to {$newDateStr} at {$newTimeStr}.";
            if (!empty($reasonText)) {
                $logNote .= " Reason: {$reasonText}.";
            }

            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'user_id' => $request->user() ? $request->user()->id : null,
                'from_status' => $currentStatus,
                'to_status' => $currentStatus,
                'note' => $logNote,
            ]);

            $refreshed = $booking->fresh(['doctor.department', 'department', 'hmoCompany', 'patient', 'statusLogs']);
            if (!$request->user('sanctum')) {
                $refreshed->doctor?->maskForPublic();
                if ($refreshed->doctor) {
                    $refreshed->doctor_name = $refreshed->doctor->initial_name;
                }
            }

            return response()->json([
                'message' => "Appointment successfully rescheduled to {$newDateStr} at {$newTimeStr}.",
                'booking' => $refreshed,
            ]);
        });
    }

    public function disabled(): JsonResponse
    {
        $bookings = Booking::with(['doctor', 'patient'])
            ->where('is_active', false)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function restore($id): JsonResponse
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();
        $booking->update([
            'is_active' => true,
            'status' => 'Confirmed',
            'delete_reason' => null,
        ]);

        return response()->json([
            'message' => 'Booking restored successfully',
            'booking' => $booking->fresh(),
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $key = $request->input('security_key');
        if ($key !== 'RESET_CONFIRM_ISALU') {
            return response()->json(['error' => 'Invalid confirmation security key', 'status' => 403], 403);
        }

        Booking::query()->delete();
        Patient::query()->delete();
        Payment::query()->delete();

        return response()->json(['message' => 'All appointment bookings and patient visit logs have been purged.']);
    }
}
