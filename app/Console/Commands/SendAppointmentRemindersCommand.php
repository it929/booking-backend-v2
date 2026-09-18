<?php

namespace App\Console\Commands;

use App\Mail\AppointmentReminderMail;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAppointmentRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders 
                            {--dry-run : Simulate execution without dispatching SMS/Email or updating database}
                            {--force-id= : Force send reminder for a specific booking ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated scan and dispatch of SMS & Email reminders 3 hours prior to clinic appointments';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService): int
    {
        $timezone = config('app.timezone', 'Africa/Lagos');
        $now = Carbon::now($timezone);
        $isDryRun = (bool) $this->option('dry-run');
        $forceId = $this->option('force-id');

        // Retrieve settings dynamically from database app_settings (no hardcoding)
        $leadHours = (int) AppSetting::getSetting('reminder_lead_hours', 3);
        $hospitalName = AppSetting::getSetting('hospital_name', config('app.name', 'Isalu Hospitals'));
        $hospitalPhone = AppSetting::getSetting('hospital_phone', '+234 800 47258 2273');

        $this->info("=== Starting Appointment Reminder Dispatch ===");
        $this->info("Current Time: {$now->toDateTimeString()} ({$timezone})");
        $this->info("Reminder Lead Time: {$leadHours} hours prior to clinic start");
        if ($isDryRun) {
            $this->warn("DRY RUN MODE ENABLED: No SMS/Emails will be sent and no database records modified.");
        }

        // 1. Build Query
        $query = Booking::with(['doctor.department', 'department', 'hmoCompany'])
            ->where('is_active', true)
            ->whereNotIn('status', ['Cancelled', 'Completed', 'Rejected', 'Deleted']);

        if ($forceId) {
            $query->where('id', $forceId);
        } else {
            // Unsent reminders only
            $query->whereNull('reminder_sent_at');

            // Today and tomorrow's upcoming slots
            $todayStr = $now->format('Y-m-d');
            $tomorrowStr = $now->copy()->addDay()->format('Y-m-d');
            $query->whereIn('appointment_date', [$todayStr, $tomorrowStr]);
        }

        $candidates = $query->get();
        $this->info("Found {$candidates->count()} active booking candidates for reminder evaluation.");

        $dispatchedCount = 0;

        foreach ($candidates as $booking) {
            $dateStr = $booking->appointment_date ? Carbon::parse($booking->appointment_date)->format('Y-m-d') : null;
            $timeStr = (string) ($booking->appointment_time ?: ($booking->time ?: ''));

            if (!$dateStr || !$timeStr) {
                $this->line("Skipping Booking #{$booking->id} ({$booking->reference_code}): missing date or time.");
                continue;
            }

            $clinicStart = $this->parseClinicStartTime($timeStr, $dateStr, $timezone);
            if (!$clinicStart) {
                $this->line("Skipping Booking #{$booking->id} ({$booking->reference_code}): could not parse start time from '{$timeStr}'.");
                continue;
            }

            // Target reminder time is exactly leadHours prior to clinic start (e.g. 11:00 AM for a 2:00 PM clinic)
            $reminderTargetTime = $clinicStart->copy()->subHours($leadHours);

            if (!$forceId) {
                // Must be at or past the reminder target time, but before the clinic start
                if ($now->lessThan($reminderTargetTime)) {
                    // Not time yet (more than 3 hours away)
                    continue;
                }

                if ($now->greaterThanOrEqualTo($clinicStart)) {
                    // Clinic has already started or passed
                    continue;
                }
            }

            $doctorInitialName = $booking->doctor_initial_name;
            $specialty = $booking->doctor_specialty
                ?: ($booking->department?->name
                ?: ($booking->doctor?->department?->name
                ?: 'Specialist Consultation'));

            $this->info("-> Eligible Booking #{$booking->id} ({$booking->reference_code}): Patient: {$booking->patient_name}, Doctor: {$doctorInitialName}, Clinic Start: {$clinicStart->toDateTimeString()}");

            if ($isDryRun) {
                $this->line("   [DRY-RUN] Would send SMS to {$booking->patient_phone} and Email to " . ($booking->patient_email ?: 'N/A'));
                $dispatchedCount++;
                continue;
            }

            // 2. Build and Dispatch SMS
            // Note: Per explicit user requirement, only doctor initial name is used (no full doctor name)
            $smsText = "Reminder: Your appointment with {$doctorInitialName} ({$specialty}) at {$hospitalName} is scheduled for today at {$timeStr}. Ref: {$booking->reference_code}. Please arrive 15m early. Helpline: {$hospitalPhone}";

            $smsResult = ['success' => false, 'status' => 'skipped', 'error' => 'No phone number'];
            if (!empty($booking->patient_phone)) {
                $smsResult = $smsService->send($booking->patient_phone, $smsText);
            }

            // 3. Build and Dispatch Email
            $emailStatus = 'skipped';
            if (!empty($booking->patient_email) && filter_var($booking->patient_email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($booking->patient_email)->send(new AppointmentReminderMail($booking));
                    $emailStatus = 'sent';
                } catch (\Throwable $e) {
                    $emailStatus = 'failed';
                    Log::error("Failed to send reminder email to {$booking->patient_email} for booking #{$booking->id}: " . $e->getMessage());
                }
            }

            // 4. Update Booking record in database
            $booking->update([
                'reminder_sent_at' => now(),
                'reminder_sms_status' => $smsResult['status'],
                'reminder_email_status' => $emailStatus,
            ]);

            // 5. Audit Log in database (booking_status_logs)
            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'user_id' => null,
                'from_status' => (string) ($booking->status?->value ?? $booking->status),
                'to_status' => (string) ($booking->status?->value ?? $booking->status),
                'note' => "Automated {$leadHours}-hour clinic reminder sent to {$booking->patient_phone} (SMS: {$smsResult['status']}) and " . ($booking->patient_email ?: 'N/A') . " (Email: {$emailStatus}). Doctor: {$doctorInitialName}.",
            ]);

            $dispatchedCount++;
            $this->info("   Dispatched: SMS [{$smsResult['status']}], Email [{$emailStatus}] for Ticket {$booking->reference_code}");
        }

        $this->info("Reminder evaluation complete. Dispatched reminders to {$dispatchedCount} booking(s).");
        return Command::SUCCESS;
    }

    /**
     * Parse clinic start time from a string like "02:00 PM – 05:00 PM", "2:00 PM to 5:00 PM", "2pm to 5pm", or "08:00 AM".
     */
    protected function parseClinicStartTime(string $timeString, string $dateString, string $timezone): ?Carbon
    {
        try {
            $clean = trim($timeString);
            // Split by -, –, or "to"
            $parts = preg_split('/\s*(?:–|-|\bto\b)\s*/i', $clean);
            $firstPart = trim($parts[0] ?? $clean);

            // Normalize "2pm" -> "2:00 PM"
            if (preg_match('/^(\d{1,2})\s*(am|pm)$/i', $firstPart, $m)) {
                $firstPart = $m[1] . ':00 ' . strtoupper($m[2]);
            }

            $datePart = trim(explode('T', $dateString)[0]);
            return Carbon::parse("{$datePart} {$firstPart}", $timezone);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
