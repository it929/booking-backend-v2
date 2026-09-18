<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Doctor;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'surname')) {
                $table->string('surname')->nullable()->after('name');
            }
            if (!Schema::hasColumn('doctors', 'middlename')) {
                $table->string('middlename')->nullable()->after('surname');
            }
            if (!Schema::hasColumn('doctors', 'lastname')) {
                $table->string('lastname')->nullable()->after('middlename');
            }
        });

        // Backfill existing doctors
        try {
            $doctors = Doctor::all();
            foreach ($doctors as $doc) {
                $raw = $doc->full_name ?: $doc->name;
                if ($raw && empty($doc->surname)) {
                    $clean = preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|nurse|pharm)\.?\b/i', '', $raw);
                    $parts = array_values(array_filter(preg_split('/[\s\-_.]+/', trim($clean))));
                    if (count($parts) === 1) {
                        $doc->surname = $parts[0];
                    } elseif (count($parts) === 2) {
                        $doc->surname = $parts[0];
                        $doc->middlename = $parts[1];
                    } elseif (count($parts) >= 3) {
                        $doc->surname = $parts[0];
                        $doc->middlename = $parts[1];
                        $doc->lastname = implode(' ', array_slice($parts, 2));
                    }
                    $doc->save();
                }
            }
        } catch (\Throwable $e) {
            // Ignore backfill errors if any
        }
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('doctors', 'surname')) {
                $dropCols[] = 'surname';
            }
            if (Schema::hasColumn('doctors', 'middlename')) {
                $dropCols[] = 'middlename';
            }
            if (Schema::hasColumn('doctors', 'lastname')) {
                $dropCols[] = 'lastname';
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
