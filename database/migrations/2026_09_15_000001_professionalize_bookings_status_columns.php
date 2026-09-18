<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Make hmo_status nullable first so it can accept NULL
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('hmo_status', 100)->nullable()->default(null)->change();
            $table->string('payment_type', 100)->default('Private Self-Pay')->change();
            $table->string('payment_status', 100)->default('Pending')->change();
            $table->string('status', 100)->default('Confirmed')->change();
        });

        // 2. Clean existing records: convert anti-pattern 'N/A' to true NULL
        DB::table('bookings')
            ->where('hmo_status', 'N/A')
            ->orWhere('hmo_status', '')
            ->update(['hmo_status' => null]);

        // 3. Add indices for faster lookup
        Schema::table('bookings', function (Blueprint $table) {
            try {
                $table->index('payment_status', 'bookings_payment_status_index');
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
            try {
                $table->index('hmo_status', 'bookings_hmo_status_index');
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
            try {
                $table->index('payment_type', 'bookings_payment_type_index');
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('hmo_status', 100)->default('N/A')->change();
        });
    }
};
