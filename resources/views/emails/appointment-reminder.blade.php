<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reminder - {{ $hospitalName }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
                    
                    <!-- Header Banner -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 32px 28px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0 0 6px 0; letter-spacing: -0.02em;">
                                {{ $hospitalName }}
                            </h1>
                            <p style="color: #ccfbf1; font-size: 13px; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">
                                Clinical Appointment Reminder • 3 Hours Remaining
                            </p>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 32px 28px;">
                            <p style="font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 20px 0;">
                                Dear <strong>{{ $booking->patient_name }}</strong>,
                            </p>
                            <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 24px 0;">
                                This is a friendly reminder that your upcoming specialist consultation is scheduled in <strong>approximately 3 hours</strong> today.
                            </p>

                            <!-- Appointment Summary Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding-bottom: 12px; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                                                    Ticket Reference
                                                </td>
                                                <td align="right" style="padding-bottom: 12px; font-size: 16px; font-family: monospace; font-weight: 900; color: #0f766e;">
                                                    {{ $booking->reference_code }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px; font-size: 12px; color: #64748b; font-weight: 600;">
                                                    Specialist Consultant
                                                </td>
                                                <td align="right" style="padding-bottom: 12px; font-size: 14px; font-weight: 700; color: #0f172a;">
                                                    {{ $doctorInitialName }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px; font-size: 12px; color: #64748b; font-weight: 600;">
                                                    Department / Specialty
                                                </td>
                                                <td align="right" style="padding-bottom: 12px; font-size: 13px; font-weight: 600; color: #0d9488;">
                                                    {{ $specialtyName }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px; font-size: 12px; color: #64748b; font-weight: 600;">
                                                    Scheduled Date
                                                </td>
                                                <td align="right" style="padding-bottom: 12px; font-size: 13px; font-weight: 700; color: #0f172a;">
                                                    {{ $booking->appointment_date ? \Carbon\Carbon::parse($booking->appointment_date)->format('d M Y') : 'Today' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px; font-size: 12px; color: #64748b; font-weight: 600;">
                                                    Clinic Time Slot
                                                </td>
                                                <td align="right" style="padding-bottom: 12px; font-size: 14px; font-weight: 800; color: #0f172a;">
                                                    {{ $booking->appointment_time ?: ($booking->time ?: 'Scheduled Slot') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 12px; color: #64748b; font-weight: 600;">
                                                    Payment / Billing
                                                </td>
                                                <td align="right" style="font-size: 13px; font-weight: 700; color: #334155;">
                                                    @if($booking->is_hmo || stripos($booking->payment_type ?? '', 'hmo') !== false)
                                                        HMO ({{ $booking->hmo_name ?: ($booking->hmoCompany?->name ?: 'HMO Enrollee') }})
                                                    @else
                                                        Private Self-Pay ({{ $booking->payment_status->value ?? $booking->payment_status }})
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Clinic Instructions -->
                            <div style="background-color: #f0fdfa; border-left: 4px solid #0d9488; padding: 14px 16px; border-radius: 8px; margin-bottom: 24px;">
                                <h4 style="margin: 0 0 6px 0; font-size: 13px; color: #115e59; font-weight: 700;">
                                    Hospital Arrival Checklist:
                                </h4>
                                <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #0f766e; line-height: 1.6;">
                                    <li>Please arrive 15 minutes prior to your time slot for triage vitals verification.</li>
                                    <li>Have your Ticket Reference Code (<strong>{{ $booking->reference_code }}</strong>) ready at reception.</li>
                                    @if($booking->is_hmo || stripos($booking->payment_type ?? '', 'hmo') !== false)
                                        <li>Bring your physical or digital HMO ID card / policy number for desk authorization.</li>
                                    @else
                                        <li>Private consultation billing can be settled via POS / Cash at the cashdesk on arrival.</li>
                                    @endif
                                </ul>
                            </div>

                            <p style="font-size: 12px; line-height: 1.5; color: #64748b; margin: 0 0 24px 0;">
                                If you need to reschedule your consultation, please visit our online portal or contact the hospital helpline immediately.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 28px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="font-size: 12px; font-weight: 700; color: #334155; margin: 0 0 4px 0;">
                                {{ $hospitalName }}
                            </p>
                            <p style="font-size: 11px; color: #64748b; margin: 0 0 8px 0;">
                                {{ $hospitalAddress }}
                            </p>
                            <p style="font-size: 11px; color: #0d9488; font-weight: 600; margin: 0;">
                                Helpline: {{ $hospitalPhone }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
