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
}
