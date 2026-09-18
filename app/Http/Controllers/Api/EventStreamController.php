<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventStreamController extends Controller
{
    public function stream(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            // Send initial ping
            echo "event: connected\n";
            echo 'data: ' . json_encode(['status' => 'connected', 'time' => now()->toIso8601String()]) . "\n\n";
            ob_flush();
            flush();

            // Heartbeat
            $activeCount = Booking::where('is_active', true)->count();
            echo "event: hospital_sync\n";
            echo 'data: ' . json_encode(['active_bookings' => $activeCount, 'timestamp' => time()]) . "\n\n";
            ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
