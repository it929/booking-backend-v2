<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Tests\TestCase;

class DoctorPrivacyTest extends TestCase
{
    public function test_public_doctors_endpoint_masks_real_names_and_only_exposes_initials(): void
    {
        $response = $this->getJson('/api/doctors');

        $response->assertStatus(200);
        $doctors = $response->json();

        $this->assertNotEmpty($doctors, 'Doctor registry should not be empty');

        foreach ($doctors as $doc) {
            // Public callers should ONLY see initials format (e.g. "Dr. O", "Dr. AAB")
            $this->assertMatchesRegularExpression(
                '/^Dr\.\s+[A-Z]{1,5}$/',
                $doc['name'],
                "Public doctor name '{$doc['name']}' must be masked to initials format"
            );

            $this->assertMatchesRegularExpression(
                '/^Dr\.\s+[A-Z]{1,5}$/',
                $doc['full_name'],
                "Public doctor full_name '{$doc['full_name']}' must be masked to initials format"
            );

            // Surnames and private name parts must be null for public callers
            $this->assertNull($doc['surname'], "Public doctor surname must be null to protect privacy");
            $this->assertNull($doc['middlename'], "Public doctor middlename must be null to protect privacy");
            $this->assertNull($doc['lastname'], "Public doctor lastname must be null to protect privacy");

            // Acronym must be pure letters between 1 and 5 characters
            $this->assertMatchesRegularExpression(
                '/^[A-Z]{1,5}$/',
                $doc['acronym'],
                "Doctor acronym '{$doc['acronym']}' must consist strictly of 1 to 5 uppercase initials"
            );
        }
    }

    public function test_public_single_doctor_endpoint_masks_name(): void
    {
        $doctor = Doctor::first();
        $this->assertNotNull($doctor);

        $response = $this->getJson("/api/doctors/{$doctor->id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertMatchesRegularExpression('/^Dr\.\s+[A-Z]{1,5}$/', $data['name']);
        $this->assertNull($data['surname']);
        $this->assertNull($data['middlename']);
        $this->assertNull($data['lastname']);
    }

    public function test_authenticated_staff_doctors_endpoint_preserves_full_legal_names(): void
    {
        // Log in as staff
        $loginRes = $this->postJson('/api/auth/staff-login', [
            'email' => 'admin@isaluhospitals.com',
            'password' => 'admin123',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        // Fetch doctors with staff token
        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->getJson('/api/doctors');

        $response->assertStatus(200);
        $doctors = $response->json();

        // At least one doctor must have surname preserved for hospital administrative use
        $hasPreservedSurname = false;
        foreach ($doctors as $doc) {
            if (!empty($doc['surname'])) {
                $hasPreservedSurname = true;
                break;
            }
        }

        $this->assertTrue($hasPreservedSurname, 'Authenticated staff must be able to view full doctor details');
    }

    public function test_public_availability_endpoint_uses_initial_name(): void
    {
        $doctor = Doctor::first();
        $this->assertNotNull($doctor);

        $response = $this->getJson("/api/bookings/availability?doctor_id={$doctor->id}&date=2026-09-20");
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertMatchesRegularExpression(
            '/^Dr\.\s+[A-Z]{1,5}$/',
            $data['doctor_name'],
            "Availability endpoint must return masked initials for public callers"
        );
    }

    public function test_doctor_acronym_and_initial_name_derivation_logic(): void
    {
        $d1 = new Doctor(['name' => 'Dr. Olopade', 'full_name' => 'Dr. Olopade']);
        $this->assertEquals('O', $d1->acronym);
        $this->assertEquals('Dr. O', $d1->initial_name);

        $d2 = new Doctor(['name' => 'Dr. Olaniyi Owoeye', 'full_name' => 'Dr. Olaniyi Owoeye']);
        $this->assertEquals('OO', $d2->acronym);
        $this->assertEquals('Dr. OO', $d2->initial_name);

        $d3 = new Doctor(['name' => 'Dr. Adekunle Afeez Babatunde', 'full_name' => 'Dr. Adekunle Afeez Babatunde']);
        $this->assertEquals('AAB', $d3->acronym);
        $this->assertEquals('Dr. AAB', $d3->initial_name);

        $d4 = new Doctor(['name' => 'Miss Adeosun Justina', 'full_name' => 'Miss Adeosun Justina']);
        $this->assertEquals('AJ', $d4->acronym);
        $this->assertEquals('Dr. AJ', $d4->initial_name);
    }
}
