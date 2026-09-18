<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\CustomTimeSlot;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\HmoCompany;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. User Roles
        $roles = [
            [
                'id' => 1,
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full administrative control over all hospital operations, user management, and clinic configurations.',
                'primary_desk' => 'analytics',
                'allowed_desks' => ['helpdesk', 'hmo', 'cashdesk', 'analytics', 'monitor', 'users', 'clinic', 'all_patients', 'checked_in_patients', 'hmo_enrollees', 'private_patients', 'create_specialist_schedule', 'disabled_bookings'],
                'assigned_modules' => ['clinical-triage', 'hmo-insurance', 'finance-billing', 'clinic-registry', 'administration', 'executive-intelligence', 'hospital-settings'],
                'is_system_role' => true,
                'status' => true,
            ],
            [
                'id' => 2,
                'name' => 'Helpdesk Officer',
                'slug' => 'helpdesk-officer',
                'description' => 'Front-desk reception patient intake, queue checking, appointment registration, and patient directories.',
                'primary_desk' => 'helpdesk',
                'allowed_desks' => ['helpdesk', 'all_patients', 'checked_in_patients'],
                'assigned_modules' => ['clinical-triage', 'clinic-registry'],
                'is_system_role' => true,
                'status' => true,
            ],
            [
                'id' => 3,
                'name' => 'HMO Approval Officer',
                'slug' => 'hmo-officer',
                'description' => 'Verification of HMO insurance policies, authorization coding, and HMO enrollees directory.',
                'primary_desk' => 'hmo',
                'allowed_desks' => ['hmo', 'hmo_enrollees'],
                'assigned_modules' => ['hmo-insurance'],
                'is_system_role' => true,
                'status' => true,
            ],
            [
                'id' => 4,
                'name' => 'Cashdesk Billing Officer',
                'slug' => 'cashdesk-officer',
                'description' => 'Private self-pay payments clearance, billing invoicing, POS transactions, and private patient directory.',
                'primary_desk' => 'cashdesk',
                'allowed_desks' => ['cashdesk', 'private_patients'],
                'assigned_modules' => ['finance-billing'],
                'is_system_role' => true,
                'status' => true,
            ],
            [
                'id' => 5,
                'name' => 'Monitor Desk Operator',
                'slug' => 'monitor-operator',
                'description' => 'Real-time consultation queue monitoring and live TV display management.',
                'primary_desk' => 'monitor',
                'allowed_desks' => ['monitor'],
                'assigned_modules' => ['clinical-triage'],
                'is_system_role' => true,
                'status' => true,
            ],
            [
                'id' => 6,
                'name' => 'Queue Analytics Officer',
                'slug' => 'analytics-officer',
                'description' => 'Executive intelligence, department throughput metrics, and AI board summaries.',
                'primary_desk' => 'analytics',
                'allowed_desks' => ['analytics'],
                'assigned_modules' => ['executive-intelligence'],
                'is_system_role' => true,
                'status' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['id' => $roleData['id']], $roleData);
        }

        // 2. Staff Users
        $users = [
            [
                'name' => 'Dr. Chief Administrator',
                'email' => 'admin@isaluhospitals.com',
                'phone' => '+234 800 472 5822',
                'password' => Hash::make('admin123'),
                'role_id' => 1,
                'desk' => 'All Access',
                'status' => true,
            ],
            [
                'name' => 'Mrs. Adesuwa Receptionist',
                'email' => 'reception@isaluhospitals.com',
                'phone' => '+234 802 334 1120',
                'password' => Hash::make('admin123'),
                'role_id' => 2,
                'desk' => 'Helpdesk Reception',
                'status' => true,
            ],
            [
                'name' => 'Mr. Kunle HMO Officer',
                'email' => 'hmo.desk@isaluhospitals.com',
                'phone' => '+234 803 998 4431',
                'password' => Hash::make('admin123'),
                'role_id' => 3,
                'desk' => 'HMO Approval Desk',
                'status' => true,
            ],
            [
                'name' => 'Mrs. Blessing Cashier',
                'email' => 'cashdesk@isaluhospitals.com',
                'phone' => '+234 805 776 2219',
                'password' => Hash::make('admin123'),
                'role_id' => 4,
                'desk' => 'Cashdesk & Invoicing',
                'status' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }

        // 3. Authentic Clinical Departments, Specialists & Duty Schedules
        // Direct Ground Truth from "SPECIALIST SCHEDULE LATEST.docx"
        $specialistData = array (
  0 => 
  array (
    'dept' => 
    array (
      'code' => 'endocrinology',
      'name' => 'Endocrinology',
      'icon' => 'Syringe',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-olopade',
        'name' => 'Dr. Olopade',
        'full_name' => 'Dr. Olopade',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sat',
            'start' => '08:00:00',
            'end' => '11:00:00',
            'time' => '08:00 AM – 11:00 AM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-ogunwale',
        'name' => 'Dr. Ogunwale',
        'full_name' => 'Dr. Ogunwale',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Thu',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-adeolu',
        'name' => 'Dr. Adeolu',
        'full_name' => 'Dr. Adeolu',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sun',
            'start' => '15:00:00',
            'end' => '17:00:00',
            'time' => '03:00 PM – 05:00 PM',
          ),
        ),
      ),
    ),
  ),
  1 => 
  array (
    'dept' => 
    array (
      'code' => 'general-surgery',
      'name' => 'General Surgery',
      'icon' => 'Scissors',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-oshodi',
        'name' => 'Dr. Oshodi',
        'full_name' => 'Dr. Oshodi',
        'billing' => 
        array (
          0 => 'HMO Insurance',
          1 => 'Private Self-Pay',
        ),
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Thu',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-philip',
        'name' => 'Dr. Philip',
        'full_name' => 'Dr. Philip',
        'billing' => 
        array (
          0 => 'HMO Insurance',
          1 => 'Private Self-Pay',
        ),
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Fri',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-ahmad',
        'name' => 'Dr. Ahmad',
        'full_name' => 'Dr. Ahmad',
        'billing' => 
        array (
          0 => 'Private Self-Pay',
        ),
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '17:00:00',
            'end' => '19:00:00',
            'time' => '05:00 PM – 07:00 PM',
          ),
        ),
      ),
    ),
  ),
  2 => 
  array (
    'dept' => 
    array (
      'code' => 'gynaecology',
      'name' => 'Obstetrics & Gynaecology',
      'icon' => 'Heart',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-sanusi',
        'name' => 'Dr. Sanusi',
        'full_name' => 'Dr. Sanusi',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '10:00:00',
            'end' => '17:00:00',
            'time' => '10:00 AM – 05:00 PM',
          ),
          1 => 
          array (
            'day' => 'Tue',
            'start' => '10:00:00',
            'end' => '17:00:00',
            'time' => '10:00 AM – 05:00 PM',
          ),
          2 => 
          array (
            'day' => 'Fri',
            'start' => '10:00:00',
            'end' => '17:00:00',
            'time' => '10:00 AM – 05:00 PM',
          ),
          3 => 
          array (
            'day' => 'Sat',
            'start' => '10:00:00',
            'end' => '17:00:00',
            'time' => '10:00 AM – 05:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-fasasi',
        'name' => 'Dr. Fasasi',
        'full_name' => 'Dr. Fasasi',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Wed',
            'start' => '14:00:00',
            'end' => '18:00:00',
            'time' => '02:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Fri',
            'start' => '13:00:00',
            'end' => '17:00:00',
            'time' => '01:00 PM – 05:00 PM',
          ),
          2 => 
          array (
            'day' => 'Sun',
            'start' => '16:00:00',
            'end' => '19:00:00',
            'time' => '04:00 PM – 07:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-olayiwola',
        'name' => 'Dr. Olayiwola',
        'full_name' => 'Dr. Olayiwola',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '13:00:00',
            'end' => '17:00:00',
            'time' => '01:00 PM – 05:00 PM',
          ),
          1 => 
          array (
            'day' => 'Tue',
            'start' => '13:00:00',
            'end' => '17:00:00',
            'time' => '01:00 PM – 05:00 PM',
          ),
          2 => 
          array (
            'day' => 'Sat',
            'start' => '13:00:00',
            'end' => '17:00:00',
            'time' => '01:00 PM – 05:00 PM',
          ),
          3 => 
          array (
            'day' => 'Thu',
            'start' => '16:00:00',
            'end' => '19:00:00',
            'time' => '04:00 PM – 07:00 PM',
          ),
        ),
      ),
    ),
  ),
  3 => 
  array (
    'dept' => 
    array (
      'code' => 'general-physician',
      'name' => 'General Physician',
      'icon' => 'Stethoscope',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-banjoko-gp',
        'name' => 'Dr. Banjoko',
        'full_name' => 'Dr. Banjoko',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sat',
            'start' => '09:00:00',
            'end' => '11:00:00',
            'time' => '09:00 AM – 11:00 AM',
          ),
        ),
      ),
    ),
  ),
  4 => 
  array (
    'dept' => 
    array (
      'code' => 'pulmonology',
      'name' => 'Chest Physician / Pulmonology',
      'icon' => 'Wind',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ojo',
        'name' => 'Dr. Ojo',
        'full_name' => 'Dr. Ojo',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '1st & 3rd Sat',
            'start' => '13:00:00',
            'end' => '15:00:00',
            'time' => '01:00 PM – 03:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-tekobo',
        'name' => 'Dr. Tekobo',
        'full_name' => 'Dr. Tekobo',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '2nd & 4th Sat',
            'start' => '11:00:00',
            'end' => '13:00:00',
            'time' => '11:00 AM – 01:00 PM',
          ),
        ),
      ),
    ),
  ),
  5 => 
  array (
    'dept' => 
    array (
      'code' => 'cardiology',
      'name' => 'Cardiology',
      'icon' => 'HeartPulse',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-akeredolu',
        'name' => 'Dr. Akeredolu',
        'full_name' => 'Dr. Akeredolu',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Tue',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Fri',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-oladimeji',
        'name' => 'Dr. Oladimeji',
        'full_name' => 'Dr. Oladimeji',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Thu',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Sat',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-ojih',
        'name' => 'Dr. Ojih',
        'full_name' => 'Dr. Ojih',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
          1 => 
          array (
            'day' => 'Wed',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  6 => 
  array (
    'dept' => 
    array (
      'code' => 'dermatology',
      'name' => 'Dermatology',
      'icon' => 'Sparkles',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-odunowo',
        'name' => 'Dr. Odunowo',
        'full_name' => 'Dr. Odunowo',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '16:00:00',
            'end' => '17:00:00',
            'time' => '04:00 PM – 05:00 PM',
          ),
          1 => 
          array (
            'day' => 'Wed',
            'start' => '16:00:00',
            'end' => '17:00:00',
            'time' => '04:00 PM – 05:00 PM',
          ),
          2 => 
          array (
            'day' => 'Fri',
            'start' => '16:00:00',
            'end' => '17:00:00',
            'time' => '04:00 PM – 05:00 PM',
          ),
        ),
      ),
    ),
  ),
  7 => 
  array (
    'dept' => 
    array (
      'code' => 'ent',
      'name' => 'ENT Surgery (Otolaryngology)',
      'icon' => 'Ear',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ogunbiyi',
        'name' => 'Dr. Ogunbiyi',
        'full_name' => 'Dr. Ogunbiyi',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sat',
            'start' => '15:00:00',
            'end' => '17:00:00',
            'time' => '03:00 PM – 05:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-adesanya',
        'name' => 'Dr. Adesanya',
        'full_name' => 'Dr. Adesanya',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sun',
            'start' => '15:00:00',
            'end' => '17:00:00',
            'time' => '03:00 PM – 05:00 PM',
          ),
        ),
      ),
    ),
  ),
  8 => 
  array (
    'dept' => 
    array (
      'code' => 'nephrology',
      'name' => 'Nephrology',
      'icon' => 'Droplet',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-busari',
        'name' => 'Dr. Busari',
        'full_name' => 'Dr. Busari',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '1st & 3rd Sun',
            'start' => '14:00:00',
            'end' => '16:00:00',
            'time' => '02:00 PM – 04:00 PM',
          ),
        ),
      ),
    ),
  ),
  9 => 
  array (
    'dept' => 
    array (
      'code' => 'haematology',
      'name' => 'Haematology',
      'icon' => 'Droplets',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-banjoko-haem',
        'name' => 'Dr. Banjoko',
        'full_name' => 'Dr. Banjoko',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sat',
            'start' => '09:00:00',
            'end' => '11:00:00',
            'time' => '09:00 AM – 11:00 AM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-owoeye',
        'name' => 'Dr. Olaniyi Owoeye',
        'full_name' => 'Dr. Olaniyi Owoeye',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Tue',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
    ),
  ),
  10 => 
  array (
    'dept' => 
    array (
      'code' => 'gastroenterology',
      'name' => 'Gastroenterology',
      'icon' => 'Activity',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ufoma-salami',
        'name' => 'Dr. Ufoma Salami',
        'full_name' => 'Dr. Ufoma Salami',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sat',
            'start' => '10:00:00',
            'end' => '13:00:00',
            'time' => '10:00 AM – 01:00 PM',
          ),
          1 => 
          array (
            'day' => 'Tue',
            'start' => '18:00:00',
            'end' => '21:00:00',
            'time' => '06:00 PM – 09:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-abubor',
        'name' => 'Dr. Abubor',
        'full_name' => 'Dr. Abubor',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '12:00:00',
            'end' => '14:00:00',
            'time' => '12:00 PM – 02:00 PM',
          ),
          1 => 
          array (
            'day' => 'Fri',
            'start' => '15:00:00',
            'end' => '17:00:00',
            'time' => '03:00 PM – 05:00 PM',
          ),
        ),
      ),
    ),
  ),
  11 => 
  array (
    'dept' => 
    array (
      'code' => 'orthopedics',
      'name' => 'Orthopedic Surgery',
      'icon' => 'Bone',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-salami',
        'name' => 'Dr. Salami',
        'full_name' => 'Dr. Salami',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '17:00:00',
            'end' => '19:00:00',
            'time' => '05:00 PM – 07:00 PM',
          ),
          1 => 
          array (
            'day' => 'Thu',
            'start' => '17:00:00',
            'end' => '19:00:00',
            'time' => '05:00 PM – 07:00 PM',
          ),
          2 => 
          array (
            'day' => 'Sat',
            'start' => '11:00:00',
            'end' => '13:00:00',
            'time' => '11:00 AM – 01:00 PM',
          ),
        ),
      ),
    ),
  ),
  12 => 
  array (
    'dept' => 
    array (
      'code' => 'paediatrics',
      'name' => 'Paediatrics & Child Health',
      'icon' => 'Baby',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-duke',
        'name' => 'Dr. Duke',
        'full_name' => 'Dr. Duke',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Tue',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Fri',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          2 => 
          array (
            'day' => 'Sat',
            'start' => '11:00:00',
            'end' => '13:00:00',
            'time' => '11:00 AM – 01:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-olayinka',
        'name' => 'Dr. Olayinka',
        'full_name' => 'Dr. Olayinka',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Wed',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Sun',
            'start' => '14:00:00',
            'end' => '16:00:00',
            'time' => '02:00 PM – 04:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-chidinma',
        'name' => 'Dr. Chidinma',
        'full_name' => 'Dr. Chidinma',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
          1 => 
          array (
            'day' => 'Thu',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
    ),
  ),
  13 => 
  array (
    'dept' => 
    array (
      'code' => 'neurology',
      'name' => 'Neurology',
      'icon' => 'Brain',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-aaron',
        'name' => 'Dr. Aaron',
        'full_name' => 'Dr. Aaron',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sun',
            'start' => '10:00:00',
            'end' => '15:00:00',
            'time' => '10:00 AM – 03:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-daniel',
        'name' => 'Dr. Daniel',
        'full_name' => 'Dr. Daniel',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Wed',
            'start' => '16:00:00',
            'end' => '18:00:00',
            'time' => '04:00 PM – 06:00 PM',
          ),
        ),
      ),
      2 => 
      array (
        'code' => 'doc-ajidahun',
        'name' => 'Dr. Ajidahun',
        'full_name' => 'Dr. Ajidahun',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sun',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  14 => 
  array (
    'dept' => 
    array (
      'code' => 'rheumatology',
      'name' => 'Rheumatology',
      'icon' => 'Bone',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-babatunde',
        'name' => 'Dr. Babatunde',
        'full_name' => 'Dr. Babatunde',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '1st & 3rd Sat',
            'start' => '11:00:00',
            'end' => '13:00:00',
            'time' => '11:00 AM – 01:00 PM',
          ),
        ),
      ),
    ),
  ),
  15 => 
  array (
    'dept' => 
    array (
      'code' => 'psychiatry',
      'name' => 'Psychiatry & Mental Health',
      'icon' => 'Smile',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-awesu',
        'name' => 'Dr. Awesu',
        'full_name' => 'Dr. Awesu',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Sun',
            'start' => '14:00:00',
            'end' => '16:00:00',
            'time' => '02:00 PM – 04:00 PM',
          ),
        ),
      ),
    ),
  ),
  16 => 
  array (
    'dept' => 
    array (
      'code' => 'dietetics',
      'name' => 'Dietetics & Clinical Nutrition',
      'icon' => 'Apple',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-adeosun',
        'name' => 'Miss Adeosun Justina',
        'full_name' => 'Miss. Adeosun Justina',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '1st & 3rd Sat',
            'start' => '14:00:00',
            'end' => '16:00:00',
            'time' => '02:00 PM – 04:00 PM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-chimah',
        'name' => 'Dr. Chimah',
        'full_name' => 'Dr. Chimah',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => '2nd & 4th Sat',
            'start' => '12:00:00',
            'end' => '14:00:00',
            'time' => '12:00 PM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  17 => 
  array (
    'dept' => 
    array (
      'code' => 'urology',
      'name' => 'Urology',
      'icon' => 'ShieldCheck',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-saliu',
        'name' => 'Dr. Saliu',
        'full_name' => 'Dr. Saliu',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '18:00:00',
            'end' => '19:00:00',
            'time' => '06:00 PM – 07:00 PM',
          ),
          1 => 
          array (
            'day' => 'Fri',
            'start' => '18:00:00',
            'end' => '19:00:00',
            'time' => '06:00 PM – 07:00 PM',
          ),
        ),
      ),
    ),
  ),
  18 => 
  array (
    'dept' => 
    array (
      'code' => 'physiotherapy',
      'name' => 'Physiotherapy & Rehabilitation',
      'icon' => 'Dumbbell',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-tope',
        'name' => 'Mr. Tope',
        'full_name' => 'Mr. Tope',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Tue',
            'start' => '11:00:00',
            'end' => '13:00:00',
            'time' => '11:00 AM – 01:00 PM',
          ),
          1 => 
          array (
            'day' => 'Sat',
            'start' => '08:00:00',
            'end' => '12:00:00',
            'time' => '08:00 AM – 12:00 PM',
          ),
          2 => 
          array (
            'day' => 'Thu',
            'start' => '09:00:00',
            'end' => '11:00:00',
            'time' => '09:00 AM – 11:00 AM',
          ),
        ),
      ),
      1 => 
      array (
        'code' => 'doc-ebunoluwa',
        'name' => 'Miss Ebunoluwa',
        'full_name' => 'Miss Ebunoluwa',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '09:00:00',
            'end' => '17:00:00',
            'time' => '09:00 AM – 05:00 PM',
          ),
          1 => 
          array (
            'day' => 'Tue',
            'start' => '09:00:00',
            'end' => '17:00:00',
            'time' => '09:00 AM – 05:00 PM',
          ),
          2 => 
          array (
            'day' => 'Wed',
            'start' => '09:00:00',
            'end' => '17:00:00',
            'time' => '09:00 AM – 05:00 PM',
          ),
          3 => 
          array (
            'day' => 'Thu',
            'start' => '09:00:00',
            'end' => '17:00:00',
            'time' => '09:00 AM – 05:00 PM',
          ),
          4 => 
          array (
            'day' => 'Fri',
            'start' => '09:00:00',
            'end' => '17:00:00',
            'time' => '09:00 AM – 05:00 PM',
          ),
        ),
      ),
    ),
  ),
  19 => 
  array (
    'dept' => 
    array (
      'code' => 'paediatric-surgery',
      'name' => 'Paediatric Surgery',
      'icon' => 'Scissors',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-abdusalam',
        'name' => 'Dr. Abdusalam',
        'full_name' => 'Dr. Abdusalam',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '17:00:00',
            'end' => '19:00:00',
            'time' => '05:00 PM – 07:00 PM',
          ),
        ),
      ),
    ),
  ),
  20 => 
  array (
    'dept' => 
    array (
      'code' => 'plastic-surgery',
      'name' => 'Plastic Surgery',
      'icon' => 'Sparkles',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-osisanya',
        'name' => 'Dr. Osisanya',
        'full_name' => 'Dr. Osisanya',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
          1 => 
          array (
            'day' => 'Wed',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
          2 => 
          array (
            'day' => 'Fri',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  21 => 
  array (
    'dept' => 
    array (
      'code' => 'neuro-surgery',
      'name' => 'Neurosurgery',
      'icon' => 'Brain',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ayodele',
        'name' => 'Dr. Ayodele',
        'full_name' => 'Dr. Ayodele',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Tue',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
          1 => 
          array (
            'day' => 'Thu',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  22 => 
  array (
    'dept' => 
    array (
      'code' => 'oncology',
      'name' => 'Oncology',
      'icon' => 'Activity',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-oyekan',
        'name' => 'Dr. Oyekan',
        'full_name' => 'Dr. Oyekan',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Wed',
            'start' => '13:00:00',
            'end' => '15:00:00',
            'time' => '01:00 PM – 03:00 PM',
          ),
        ),
      ),
    ),
  ),
  23 => 
  array (
    'dept' => 
    array (
      'code' => 'maxillofacial',
      'name' => 'Maxillofacial Surgery',
      'icon' => 'Ear',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ochuko',
        'name' => 'Dr. Ochuko',
        'full_name' => 'Dr. Ochuko',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Mon',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
          1 => 
          array (
            'day' => 'Thu',
            'start' => '10:00:00',
            'end' => '14:00:00',
            'time' => '10:00 AM – 02:00 PM',
          ),
        ),
      ),
    ),
  ),
  24 => 
  array (
    'dept' => 
    array (
      'code' => 'cardiothoracic',
      'name' => 'Cardiothoracic Surgery',
      'icon' => 'HeartPulse',
    ),
    'doctors' => 
    array (
      0 => 
      array (
        'code' => 'doc-ajibade',
        'name' => 'Dr. Ajibade',
        'full_name' => 'Dr. Ajibade',
        'shifts' => 
        array (
          0 => 
          array (
            'day' => 'Wed',
            'start' => '13:00:00',
            'end' => '15:00:00',
            'time' => '01:00 PM – 03:00 PM',
          ),
        ),
      ),
    ),
  ),
);

        $schedCounter = 1;
        foreach ($specialistData as $group) {
            $deptData = $group['dept'];
            $dept = Department::updateOrCreate(
                ['code' => $deptData['code']],
                [
                    'code' => $deptData['code'],
                    'name' => $deptData['name'],
                    'icon_name' => $deptData['icon'],
                    'status' => true,
                ]
            );

            foreach ($group['doctors'] as $docData) {
                $billing = $docData['billing'] ?? ['Private Self-Pay', 'HMO Insurance'];
                $cleanName = trim(preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $docData['name']));
                $nameParts = preg_split('/[\s\-_.]+/', $cleanName, -1, PREG_SPLIT_NO_EMPTY);
                $docAcronym = '';
                foreach ($nameParts as $np) {
                    if (preg_match('/[a-zA-Z]/', $np, $m)) {
                        $docAcronym .= strtoupper($m[0]);
                    }
                }
                $docAcronym = !empty($docAcronym) ? $docAcronym : 'DOC';

                $doctor = Doctor::updateOrCreate(
                    ['code' => $docData['code']],
                    [
                        'code' => $docData['code'],
                        'name' => $docData['name'],
                        'full_name' => $docData['full_name'],
                        'acronym' => $docAcronym,
                        'department_id' => $dept->id,
                        'accepts_private' => in_array('Private Self-Pay', $billing),
                        'accepts_hmo' => in_array('HMO Insurance', $billing),
                        'consultation_fee' => 0.00,
                        'status' => true,
                    ]
                );

                foreach ($docData['shifts'] as $shift) {
                    $schedCode = 'sched-' . $schedCounter++;
                    [$dayOfWeek, $recType, $recWeeks] = DoctorSchedule::normalizeDayAndRecurrence($shift['day']);
                    DoctorSchedule::updateOrCreate(
                        ['doctor_id' => $doctor->id, 'day_of_week' => $dayOfWeek],
                        [
                            'code' => $schedCode,
                            'doctor_id' => $doctor->id,
                            'day_of_week' => $dayOfWeek,
                            'recurrence_type' => $recType,
                            'recurrence_weeks' => $recWeeks,
                            'shift_name' => $deptData['name'] . ' Clinic',
                            'start_time' => $shift['start'],
                            'end_time' => $shift['end'],
                            'capacity' => 15,
                            'slot_duration_minutes' => 30,
                            'room' => '',
                            'total_weekly_capacity' => 15,
                            'status' => true,
                        ]
                    );
                }
            }
        }

        // 4. HMO Companies
        $hmos = [
            ['code' => 'hmo-1', 'name' => 'Hygeia HMO', 'policy_code' => 'HYG-9021', 'email' => 'preauth@hygeiahmo.com', 'phone' => '+234 700 494 342', 'contact_person' => 'Mrs. Toyin Adeyemi'],
            ['code' => 'hmo-2', 'name' => 'Reliance HMO', 'policy_code' => 'REL-4412', 'email' => 'claims@reliancehmo.com', 'phone' => '+234 1 700 1570', 'contact_person' => 'Mr. Femi Ogunleye'],
            ['code' => 'hmo-3', 'name' => 'AXA Mansard Health', 'policy_code' => 'AXA-8819', 'email' => 'care@axamansard.com', 'phone' => '+234 1 448 5433', 'contact_person' => 'Dr. Sandra Okafor'],
            ['code' => 'hmo-4', 'name' => 'Leadway Health HMO', 'policy_code' => 'LAD-3011', 'email' => 'medical@leadwayhealth.com', 'phone' => '+234 1 280 1000', 'contact_person' => 'Mr. Segun Alabi'],
            ['code' => 'hmo-5', 'name' => 'Avon HMO', 'policy_code' => 'AVN-5520', 'email' => 'approvals@avonhmo.com', 'phone' => '+234 700 286 6466', 'contact_person' => 'Mrs. Chidimma Nwosu'],
            ['code' => 'hmo-6', 'name' => 'Clearline HMO', 'policy_code' => 'CLR-7710', 'email' => 'approvals@clearlinehmo.com', 'phone' => '+234 1 270 3340', 'contact_person' => 'Mr. Bamidele Adeleke'],
            ['code' => 'hmo-7', 'name' => 'Total Health Trust', 'policy_code' => 'THT-1120', 'email' => 'preauth@totalhealthtrust.com', 'phone' => '+234 700 868 2543', 'contact_person' => 'Dr. Ngozi Okeke'],
            ['code' => 'hmo-8', 'name' => 'Redcare HMO', 'policy_code' => 'RDC-3390', 'email' => 'care@redcarehmo.com', 'phone' => '+234 700 733 2273', 'contact_person' => 'Mrs. Zainab Umar'],
        ];

        foreach ($hmos as $hmoData) {
            HmoCompany::updateOrCreate(['code' => $hmoData['code']], $hmoData);
        }

        // 5. Custom Time Slots
        $slots = [
            ['code' => 'slot-1', 'label' => '08:00 AM – 10:00 AM'],
            ['code' => 'slot-2', 'label' => '10:00 AM – 12:00 PM'],
            ['code' => 'slot-3', 'label' => '12:00 PM – 02:00 PM'],
            ['code' => 'slot-4', 'label' => '02:00 PM – 04:00 PM'],
            ['code' => 'slot-5', 'label' => '04:00 PM – 06:00 PM'],
        ];

        foreach ($slots as $slotData) {
            CustomTimeSlot::updateOrCreate(['code' => $slotData['code']], $slotData);
        }

        // 6. App Settings
        $settings = [
            [
                'key' => 'hospital_info',
                'value' => [
                    'name' => 'Isalu Hospitals',
                    'tagline' => 'Excellence in Healthcare & Specialist Services',
                    'address' => 'No. 46, Ijaiye Road, Ogba, Ikeja, Lagos, Nigeria',
                    'emergency_phone' => '+234 800 47258 2273',
                    'email' => 'info@isaluhospitals.com',
                    'opening_hours' => '24 Hours Daily (Emergency & ICU Open)',
                ],
            ],
            [
                'key' => 'gynaecology_announcement',
                'value' => [
                    'title' => 'Obstetrics & Gynaecology Consultations Are Available Everyday!',
                    'description' => 'At Isalu Hospitals, our Obstetrics & Gynaecology specialists are on duty 7 days a week for comprehensive women healthcare, prenatal consultations, and fertility evaluations.',
                    'enabled' => true,
                ],
            ],
        ];

        foreach ($settings as $settingData) {
            AppSetting::updateOrCreate(['key' => $settingData['key']], $settingData);
        }
    }
}