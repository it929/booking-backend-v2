<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->string('acronym')->nullable();
            $table->string('qualification')->default('MBBS, FWACS');
            $table->text('bio')->nullable();
            $table->text('image_url')->nullable();
            $table->json('accepted_patient_types')->nullable();
            $table->decimal('consultation_fee', 10, 2)->default(0.00);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
