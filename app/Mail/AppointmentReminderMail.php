<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public string $doctorInitialName;
    public string $hospitalName;
    public string $hospitalPhone;
    public string $hospitalAddress;
    public string $specialtyName;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking->loadMissing(['doctor.department', 'department', 'hmoCompany']);

        // Use doctor initial name per strict requirement (no full doctor name)
        $this->doctorInitialName = $this->booking->doctor_initial_name;

        // Retrieve hospital details dynamically from database app_settings (no hardcoding)
        $this->hospitalName = AppSetting::getSetting('hospital_name', config('app.name', 'Isalu Hospitals'));
        $this->hospitalPhone = AppSetting::getSetting('hospital_phone', '+234 800 47258 2273');
        $this->hospitalAddress = AppSetting::getSetting('hospital_address', 'Wempco Road, Ogba, Ikeja, Lagos, Nigeria');

        // Specialty / Department
        $this->specialtyName = $this->booking->doctor_specialty
            ?: ($this->booking->department?->name
            ?: ($this->booking->doctor?->department?->name
            ?: 'Specialist Consultation'));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $timeStr = $this->booking->appointment_time ?: ($this->booking->time ?: 'Scheduled Time');

        return new Envelope(
            subject: "Appointment Reminder (3 Hours): Clinic with {$this->doctorInitialName} at {$timeStr} - {$this->hospitalName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-reminder',
            text: 'emails.appointment-reminder-text',
            with: [
                'booking' => $this->booking,
                'doctorInitialName' => $this->doctorInitialName,
                'hospitalName' => $this->hospitalName,
                'hospitalPhone' => $this->hospitalPhone,
                'hospitalAddress' => $this->hospitalAddress,
                'specialtyName' => $this->specialtyName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
