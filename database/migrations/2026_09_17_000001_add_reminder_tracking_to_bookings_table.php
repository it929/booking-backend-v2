<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('reminder_sent_at')->nullable()->after('completed_at')->index();
            $table->string('reminder_sms_status', 50)->nullable()->after('reminder_sent_at');
            $table->string('reminder_email_status', 50)->nullable()->after('reminder_sms_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at']);
            $table->dropColumn(['reminder_sent_at', 'reminder_sms_status', 'reminder_email_status']);
        });
    }
};
