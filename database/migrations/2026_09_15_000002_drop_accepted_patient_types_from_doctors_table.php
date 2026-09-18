<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doctors') && Schema::hasColumn('doctors', 'accepted_patient_types')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropColumn('accepted_patient_types');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doctors') && !Schema::hasColumn('doctors', 'accepted_patient_types')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->json('accepted_patient_types')->nullable()->after('image_url');
            });
        }
    }
};
