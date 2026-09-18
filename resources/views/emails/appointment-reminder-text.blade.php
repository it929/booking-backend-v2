{{ $hospitalName }} - CLINICAL APPOINTMENT REMINDER (3 Hours Remaining)

Dear {{ $booking->patient_name }},

This is a reminder that your specialist consultation is scheduled in approximately 3 hours today.

APPOINTMENT DETAILS:
- Ticket Reference: {{ $booking->reference_code }}
- Specialist Consultant: {{ $doctorInitialName }}
- Specialty: {{ $specialtyName }}
- Date: {{ $booking->appointment_date ? \Carbon\Carbon::parse($booking->appointment_date)->format('d M Y') : 'Today' }}
- Clinic Time Slot: {{ $booking->appointment_time ?: ($booking->time ?: 'Scheduled Slot') }}
- Payment: {{ $booking->is_hmo ? 'HMO Insurance' : 'Private Self-Pay' }}

HOSPITAL ARRIVAL ADVICE:
- Please arrive at least 15 minutes before your time slot for reception check-in.
- Present your ticket reference code ({{ $booking->reference_code }}) on arrival.
@if($booking->is_hmo)
- Present your HMO card / policy code for insurance authorization.
@endif

Hospital Address: {{ $hospitalAddress }}
Helpline: {{ $hospitalPhone }}
