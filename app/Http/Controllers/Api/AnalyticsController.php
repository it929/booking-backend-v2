<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');

        $totalBookings = Booking::where('is_active', true)->count();
        $todayBookings = Booking::where('is_active', true)->whereDate('appointment_date', $today)->count();
        $checkedInToday = Booking::where('is_active', true)->whereDate('appointment_date', $today)->where('status', 'Checked In')->count();
        $pendingHmoApprovals = Booking::where('is_active', true)->where('hmo_status', 'Pending Approval')->count();
        $pendingCashdesk = Booking::where('is_active', true)->where('payment_type', 'Private Self-Pay')->where('payment_status', 'Pending')->count();
        $totalRevenue = Payment::where('payment_status', 'Paid')->sum('amount');
        $todayRevenue = Payment::where('payment_status', 'Paid')->whereDate('paid_at', $today)->sum('amount');

        // Department breakdown
        $departments = Department::withCount(['bookings' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($d) {
            return [
                'name' => $d->name,
                'count' => $d->bookings_count,
            ];
        });

        // Payment type breakdown
        $hmoCount = Booking::where('is_active', true)->where('payment_type', 'like', '%HMO%')->count();
        $selfPayCount = Booking::where('is_active', true)->where('payment_type', 'not like', '%HMO%')->count();

        return response()->json([
            'total_bookings' => $totalBookings,
            'today_bookings' => $todayBookings,
            'checked_in_today' => $checkedInToday,
            'pending_hmo_approvals' => $pendingHmoApprovals,
            'pending_cashdesk_invoices' => $pendingCashdesk,
            'total_revenue' => (float) $totalRevenue,
            'today_revenue' => (float) $todayRevenue,
            'departments_breakdown' => $departments,
            'patient_type_breakdown' => [
                'hmo' => $hmoCount,
                'self_pay' => $selfPayCount,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function aiReport(Request $request): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');
        $totalActive = Booking::where('is_active', true)->count();
        $checkedIn = Booking::where('is_active', true)->where('status', 'Checked In')->count();
        $hmoPending = Booking::where('is_active', true)->where('hmo_status', 'Pending Approval')->count();
        $revenue = Payment::where('payment_status', 'Paid')->sum('amount');

        $topDepartment = Department::withCount('bookings')->orderBy('bookings_count', 'desc')->first();
        $topDeptName = $topDepartment ? $topDepartment->name : 'General Outpatient';

        $reportText = "### Isalu Hospitals - Executive Clinic Operations & Intelligence Report\n\n" .
            "**Date:** " . Carbon::now()->format('l, F j, Y - H:i:s') . "\n\n" .
            "#### 1. Patient Intake & Queue Dynamics\n" .
            "- **Active Appointments:** A total of **{$totalActive} active bookings** are currently in the system.\n" .
            "- **Queue Throughput:** **{$checkedIn} patients** have completed triage intake and are currently checked in or consulting.\n" .
            "- **Specialist Demand:** **{$topDeptName}** is experiencing the highest patient volume this period.\n\n" .
            "#### 2. HMO Insurance Clearance & Pre-Authorizations\n" .
            "- **Pending HMO Claims:** **{$hmoPending} insurance enrollees** require authorization code clearance.\n" .
            "- **Operational Recommendation:** HMO desk officers should prioritize pre-authorizations for arriving patients to minimize consultation wait times.\n\n" .
            "#### 3. Financial & Revenue Performance\n" .
            "- **Cashdesk Revenue Settled:** **₦" . number_format($revenue, 2) . "** has been captured in verified billing payments.\n" .
            "- **Billing Velocity:** Fast POS clearance at Cashdesk is maintaining healthy front-desk throughput.\n\n" .
            "#### 4. Clinical Capacity Optimization\n" .
            "- Recommend adjusting afternoon slot capacity in Pediatrics and Obstetrics & Gynaecology to accommodate peak walk-in surges.\n" .
            "- Ensure doctor shift rosters are updated 24 hours in advance to guarantee zero patient turnaround delays.";

        return response()->json([
            'report' => $reportText,
            'title' => 'Isalu Hospitals Operational Intelligence Board',
            'generated_at' => now()->toIso8601String(),
            'status' => 'success',
        ]);
    }
}
