<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE `doctors` MODIFY `qualification` VARCHAR(255) NULL DEFAULT NULL");
        } catch (\Throwable $e) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->string('qualification')->nullable()->default(null)->change();
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE `doctors` MODIFY `qualification` VARCHAR(255) NOT NULL DEFAULT 'MBBS, FWACS'");
        } catch (\Throwable $e) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->string('qualification')->default('MBBS, FWACS')->change();
            });
        }
    }
};
