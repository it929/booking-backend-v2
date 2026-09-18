<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn')->unique();
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable()->index();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('hmo_company_id')->nullable()->constrained('hmo_companies')->nullOnDelete();
            $table->string('hmo_policy_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
