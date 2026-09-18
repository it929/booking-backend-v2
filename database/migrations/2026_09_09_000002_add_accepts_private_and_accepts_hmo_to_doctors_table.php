<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'accepts_private')) {
                $table->boolean('accepts_private')->default(true)->after('accepted_patient_types');
            }
            if (!Schema::hasColumn('doctors', 'accepts_hmo')) {
                $table->boolean('accepts_hmo')->default(true)->after('accepts_private');
            }
        });

        try {
            DB::statement('CREATE INDEX idx_doctors_billing_flags ON doctors (accepts_private, accepts_hmo)');
        } catch (\Throwable $e) {
            // Index already exists or duplicate key name
        }

        // Data migration: accurately backfill boolean flags from existing data
        $doctors = DB::table('doctors')->get(['id', 'accepted_patient_types']);
        foreach ($doctors as $d) {
            $raw = $d->accepted_patient_types;
            $types = [];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $types = $decoded;
                }
            } elseif (is_array($raw)) {
                $types = $raw;
            }

            $hasPrivate = true;
            $hasHmo = true;

            if (!empty($types)) {
                $foundPrivate = false;
                $foundHmo = false;
                foreach ($types as $t) {
                    if (is_string($t)) {
                        if (preg_match('/private|self-pay/i', $t)) $foundPrivate = true;
                        if (preg_match('/hmo/i', $t)) $foundHmo = true;
                    }
                }
                if ($foundPrivate || $foundHmo) {
                    $hasPrivate = $foundPrivate;
                    $hasHmo = $foundHmo;
                }
            }

            DB::table('doctors')->where('id', $d->id)->update([
                'accepts_private' => $hasPrivate,
                'accepts_hmo' => $hasHmo,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            try {
                DB::statement('DROP INDEX idx_doctors_billing_flags ON doctors');
            } catch (\Throwable $e) {}

            $dropCols = [];
            if (Schema::hasColumn('doctors', 'accepts_private')) $dropCols[] = 'accepts_private';
            if (Schema::hasColumn('doctors', 'accepts_hmo')) $dropCols[] = 'accepts_hmo';
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
