<?php

namespace Tests\Feature;

use Tests\TestCase;

class BookingApiTest extends TestCase
{
    public function test_staff_login(): void
    {
        $response = $this->postJson('/api/auth/staff-login', [
            'email' => 'admin@isaluhospitals.com',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'user' => ['id', 'name', 'email', 'role', 'desk'],
                 ]);
    }

    public function test_get_departments(): void
    {
        $response = $this->getJson('/api/departments');
        $response->assertStatus(200)
                 ->assertJsonCount(21);
    }

    public function test_get_doctors(): void
    {
        $response = $this->getJson('/api/doctors');
        $response->assertStatus(200)
                 ->assertJsonCount(20);
    }

    public function test_create_and_manage_booking_flow(): void
    {
        $doctor = \App\Models\Doctor::first();
        $this->assertNotNull($doctor, 'Seeded doctor should exist');

        // Ensure active duty schedule exists for Monday (2026-09-14)
        \App\Models\DoctorSchedule::updateOrCreate(
            ['doctor_id' => $doctor->id, 'day_of_week' => 'Mon'],
            [
                'code' => 'SCHED-TEST-' . $doctor->id,
                'recurrence_type' => 'every',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'capacity' => 20,
                'status' => true,
            ]
        );

        // Clean up any existing test bookings for this date and phone
        \App\Models\Booking::where('patient_phone', 'like', '%8012345678%')
            ->whereDate('appointment_date', '2026-09-14')
            ->delete();

        // 1. Create a booking
        $createRes = $this->postJson('/api/bookings', [
            'doctor_id' => $doctor->code ?: $doctor->id,
            'date' => '2026-09-14',
            'time' => '08:00 AM – 10:00 AM',
            'patient_name' => 'John Doe Test',
            'patient_phone' => '+234 801 234 5678',
            'patient_email' => 'johndoe@example.com',
            'reason' => 'Routine cardiology wellness check',
            'payment_type' => 'Private Self-Pay',
        ]);

        $createRes->assertStatus(201)
                  ->assertJsonStructure([
                      'id',
                      'reference_code',
                      'status',
                      'patient_name',
                  ]);

        $refCode = $createRes->json('reference_code');
        $bookingId = $createRes->json('id');

        // 2. Lookup booking
        $lookupRes = $this->getJson("/api/bookings/lookup?ref_code={$refCode}");
        $lookupRes->assertStatus(200)
                  ->assertJsonPath('reference_code', $refCode);

        // 3. Frontdesk Check-in
        $checkinRes = $this->patchJson("/api/bookings/{$bookingId}/check-in");
        $checkinRes->assertStatus(200)
                   ->assertJsonPath('booking.status', 'Checked In');

        // 4. Cashdesk Payment
        $payRes = $this->patchJson("/api/bookings/{$bookingId}/pay-cashdesk", [
            'amount' => 15000,
            'payment_method' => 'POS',
        ]);
        $payRes->assertStatus(200)
               ->assertJsonPath('booking.payment_status', 'Paid');
    }

    public function test_analytics_summary(): void
    {
        $response = $this->getJson('/api/analytics/summary');
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_bookings',
                     'today_bookings',
                     'total_revenue',
                     'departments_breakdown',
                 ]);
    }

    public function test_prevent_duplicate_clinic_booking_same_day(): void
    {
        $doctor = \App\Models\Doctor::with('department')->first();
        $this->assertNotNull($doctor);

        $testDate = '2026-09-21'; // Monday
        $dayOfWeek = 'Mon';

        \App\Models\DoctorSchedule::updateOrCreate(
            ['doctor_id' => $doctor->id, 'day_of_week' => $dayOfWeek],
            [
                'code' => 'SCHED-DUP-' . $doctor->id,
                'recurrence_type' => 'every',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'capacity' => 20,
                'status' => true,
            ]
        );

        $phone = '08099887766';

        // Clean up any existing bookings for this phone on test date
        \App\Models\Booking::where('patient_phone', 'like', '%8099887766%')
            ->whereDate('appointment_date', $testDate)
            ->delete();

        // 1. Initial Booking for Clinic on Monday 21/09/2026
        $res1 = $this->postJson('/api/bookings', [
            'doctor_id' => $doctor->code ?: $doctor->id,
            'date' => $testDate,
            'time' => '09:00 AM – 11:00 AM',
            'patient_name' => 'Duplicate Test Patient',
            'patient_phone' => $phone,
            'patient_email' => 'duplicate_test@example.com',
            'reason' => 'First consultation',
            'payment_type' => 'Private Self-Pay',
        ]);

        $res1->assertStatus(201);
        $firstRef = $res1->json('reference_code');
        $firstBookingId = $res1->json('id');
        $this->assertNotEmpty($firstRef);

        // 2. Second Booking on same day for same clinic with normalized phone (+234 809 988 7766)
        $res2 = $this->postJson('/api/bookings', [
            'doctor_id' => $doctor->code ?: $doctor->id,
            'date' => $testDate,
            'time' => '11:00 AM – 01:00 PM',
            'patient_name' => 'Duplicate Test Patient',
            'patient_phone' => '+234 809 988 7766', // format variance
            'patient_email' => 'duplicate_test@example.com',
            'reason' => 'Second consultation attempt',
            'payment_type' => 'Private Self-Pay',
        ]);

        $res2->assertStatus(409)
             ->assertJsonPath('error', 'Duplicate clinic booking detected')
             ->assertJsonPath('existing_reference', $firstRef);

        // 3. Different clinic on same date should be allowed
        $doctor2 = \App\Models\Doctor::where('department_id', '!=', $doctor->department_id)->first();
        if ($doctor2) {
            \App\Models\DoctorSchedule::updateOrCreate(
                ['doctor_id' => $doctor2->id, 'day_of_week' => $dayOfWeek],
                [
                    'code' => 'SCHED-DUP2-' . $doctor2->id,
                    'recurrence_type' => 'every',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'capacity' => 20,
                    'status' => true,
                ]
            );

            $res3 = $this->postJson('/api/bookings', [
                'doctor_id' => $doctor2->code ?: $doctor2->id,
                'date' => $testDate,
                'time' => '02:00 PM – 04:00 PM',
                'patient_name' => 'Duplicate Test Patient',
                'patient_phone' => $phone,
                'patient_email' => 'duplicate_test@example.com',
                'reason' => 'Cross-clinic appointment',
                'payment_type' => 'Private Self-Pay',
            ]);

            $res3->assertStatus(201);
        }

        // Clean up
        \App\Models\Booking::where('patient_phone', 'like', '%8099887766%')
            ->whereDate('appointment_date', $testDate)
            ->delete();
    }
}
