<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CustomTimeSlotController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\EventStreamController;
use App\Http\Controllers\Api\HmoCompanyController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SystemUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Isalu Hospitals - Production REST API Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('auth/staff-login', [AuthController::class, 'login']);
Route::post('auth/token-refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
});

// Event Stream & Analytics
Route::get('stream/events', [EventStreamController::class, 'stream']);
Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
Route::post('analytics/ai-report', [AnalyticsController::class, 'aiReport']);

// Booking Custom Endpoints
Route::get('bookings/lookup', [BookingController::class, 'lookup']);
Route::get('bookings/availability', [BookingController::class, 'availability']);
Route::get('bookings/disabled', [BookingController::class, 'disabled']);
Route::post('bookings/clear-all', [BookingController::class, 'clearAll']);
Route::patch('bookings/{id}/check-in', [BookingController::class, 'checkIn']);
Route::patch('bookings/{id}/approve-hmo', [BookingController::class, 'approveHmo']);
Route::patch('bookings/{id}/pay-cashdesk', [BookingController::class, 'payCashdesk']);
Route::patch('bookings/{id}/reroute-to-cashdesk', [BookingController::class, 'rerouteToCashdesk']);
Route::patch('bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
Route::post('bookings/{id}/restore', [BookingController::class, 'restore']);

// RESTful Resources
Route::apiResource('departments', DepartmentController::class);
Route::apiResource('doctors', DoctorController::class);
Route::apiResource('schedules', DoctorScheduleController::class);
Route::apiResource('bookings', BookingController::class);
Route::apiResource('hmo-companies', HmoCompanyController::class);
Route::apiResource('users', SystemUserController::class);
Route::apiResource('roles', RoleController::class);
Route::apiResource('time-slots', CustomTimeSlotController::class);
Route::apiResource('settings', AppSettingController::class);
