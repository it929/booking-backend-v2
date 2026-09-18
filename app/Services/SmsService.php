<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS message to a recipient phone number.
     *
     * @param string $phone
     * @param string $message
     * @return array{success: bool, status: string, error: ?string}
     */
    public function send(string $phone, string $message): array
    {
        $cleanPhone = $this->normalizePhoneNumber($phone);
        if (empty($cleanPhone)) {
            Log::warning("SmsService: Invalid recipient phone number provided: '{$phone}'");
            return [
                'success' => false,
                'status' => 'failed',
                'error' => 'Invalid recipient phone number',
            ];
        }

        // Retrieve SMS driver & sender ID from database app_settings, falling back to environment/config
        $driver = AppSetting::getSetting('sms_driver', env('SMS_DRIVER', 'log'));
        $senderId = AppSetting::getSetting('sms_sender_id', env('SMS_SENDER_ID', 'IsaluHosp'));

        try {
            switch (strtolower($driver)) {
                case 'termii':
                    return $this->sendViaTermii($cleanPhone, $message, $senderId);

                case 'twilio':
                    return $this->sendViaTwilio($cleanPhone, $message);

                case 'generic_http':
                    return $this->sendViaGenericHttp($cleanPhone, $message, $senderId);

                case 'log':
                default:
                    Log::channel('single')->info("================ [SMS DISPATCH LOG] ================");
                    Log::channel('single')->info("Recipient: {$cleanPhone} (Original: {$phone})");
                    Log::channel('single')->info("Sender ID: {$senderId}");
                    Log::channel('single')->info("Content: {$message}");
                    Log::channel('single')->info("=====================================================");
                    return [
                        'success' => true,
                        'status' => 'sent',
                        'error' => null,
                    ];
            }
        } catch (\Throwable $e) {
            Log::error("SmsService Exception: " . $e->getMessage(), [
                'phone' => $cleanPhone,
                'driver' => $driver,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize Nigerian / International phone number.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone));
        if (empty($digits)) {
            return '';
        }

        // Local Nigerian number e.g. 0803... or 070...
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234' . substr($digits, 1);
        }

        // +234...
        if (str_starts_with($digits, '+')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Termii API Provider (Leading SMS gateway in Nigeria).
     */
    protected function sendViaTermii(string $phone, string $message, string $senderId): array
    {
        $apiKey = AppSetting::getSetting('termii_api_key', env('TERMII_API_KEY'));
        if (empty($apiKey)) {
            Log::error("SmsService: Termii API Key is missing in database app_settings or .env");
            return [
                'success' => false,
                'status' => 'failed',
                'error' => 'Termii API Key missing',
            ];
        }

        $endpoint = AppSetting::getSetting('termii_endpoint', 'https://api.ng.termii.com/api/sms/send');

        $response = Http::timeout(10)->post($endpoint, [
            'to' => $phone,
            'from' => $senderId,
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'generic',
            'api_key' => $apiKey,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'status' => 'sent',
                'error' => null,
            ];
        }

        $errorMsg = $response->body();
        Log::error("SmsService: Termii dispatch failed. Status: {$response->status()}, Body: {$errorMsg}");
        return [
            'success' => false,
            'status' => 'failed',
            'error' => $errorMsg,
        ];
    }

    /**
     * Twilio API Provider.
     */
    protected function sendViaTwilio(string $phone, string $message): array
    {
        $sid = AppSetting::getSetting('twilio_sid', env('TWILIO_SID'));
        $token = AppSetting::getSetting('twilio_auth_token', env('TWILIO_AUTH_TOKEN'));
        $fromNumber = AppSetting::getSetting('twilio_from', env('TWILIO_FROM'));

        if (empty($sid) || empty($token) || empty($fromNumber)) {
            Log::error("SmsService: Twilio credentials missing in database app_settings or .env");
            return [
                'success' => false,
                'status' => 'failed',
                'error' => 'Twilio credentials missing',
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $to = str_starts_with($phone, '+') ? $phone : "+{$phone}";

        $response = Http::withBasicAuth($sid, $token)
            ->timeout(10)
            ->asForm()
            ->post($url, [
                'From' => $fromNumber,
                'To' => $to,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'status' => 'sent',
                'error' => null,
            ];
        }

        $errorMsg = $response->body();
        Log::error("SmsService: Twilio dispatch failed. Status: {$response->status()}, Body: {$errorMsg}");
        return [
            'success' => false,
            'status' => 'failed',
            'error' => $errorMsg,
        ];
    }

    /**
     * Generic HTTP SMS Gateway Provider.
     */
    protected function sendViaGenericHttp(string $phone, string $message, string $senderId): array
    {
        $url = AppSetting::getSetting('generic_sms_url', env('GENERIC_SMS_URL'));
        $apiKey = AppSetting::getSetting('generic_sms_api_key', env('GENERIC_SMS_API_KEY'));

        if (empty($url)) {
            Log::error("SmsService: Generic SMS URL missing in database app_settings or .env");
            return [
                'success' => false,
                'status' => 'failed',
                'error' => 'Generic SMS URL missing',
            ];
        }

        $headers = [];
        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = Http::withHeaders($headers)->timeout(10)->post($url, [
            'to' => $phone,
            'sender' => $senderId,
            'message' => $message,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'status' => 'sent',
                'error' => null,
            ];
        }

        $errorMsg = $response->body();
        Log::error("SmsService: Generic HTTP SMS dispatch failed. Status: {$response->status()}, Body: {$errorMsg}");
        return [
            'success' => false,
            'status' => 'failed',
            'error' => $errorMsg,
        ];
    }
}
