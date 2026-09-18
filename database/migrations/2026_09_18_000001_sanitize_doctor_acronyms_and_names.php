<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sanitizes all doctor acronyms and ensures full names or titles never exist in the acronym column.
     */
    public function up(): void
    {
        $doctors = DB::table('doctors')->get();

        foreach ($doctors as $d) {
            $rawAcronym = trim($d->acronym ?? '');
            $rawName = $d->name ?: ($d->full_name ?: '');
            $cleanName = trim(preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|miss|nurse|pharm)\.?\b/i', '', $rawName));
            $parts = preg_split('/[\s\-_.]+/', $cleanName, -1, PREG_SPLIT_NO_EMPTY);
            
            $initials = '';
            foreach ($parts as $p) {
                if (preg_match('/[a-zA-Z]/', $p, $m)) {
                    $initials .= strtoupper($m[0]);
                }
            }
            if (empty($initials)) {
                $initials = 'DOC';
            }

            // If existing acronym is already valid initials (1-5 alphabetical chars, no spaces, no title prefix)
            $isAcronymValid = !empty($rawAcronym) &&
                !preg_match('/\s/', $rawAcronym) &&
                !preg_match('/^(dr|doctor|mr|mrs|ms|miss|prof)\.?/i', $rawAcronym) &&
                strlen($rawAcronym) <= 5 &&
                preg_match('/^[a-zA-Z]+$/', $rawAcronym);

            $targetAcronym = $isAcronymValid ? strtoupper($rawAcronym) : $initials;

            // Clean accidental title prefixes from surname
            $surname = $d->surname;
            $middlename = $d->middlename;
            $lastname = $d->lastname;

            if (preg_match('/^(miss|mr|mrs|dr|prof)\.?$/i', trim($surname ?? ''))) {
                $nameParts = $parts;
                if (count($nameParts) === 1) {
                    $surname = $nameParts[0];
                    $middlename = null;
                    $lastname = null;
                } elseif (count($nameParts) === 2) {
                    $surname = $nameParts[0];
                    $middlename = $nameParts[1];
                    $lastname = null;
                } else {
                    $surname = $nameParts[0];
                    $middlename = $nameParts[1];
                    $lastname = implode(' ', array_slice($nameParts, 2));
                }
            }

            DB::table('doctors')->where('id', $d->id)->update([
                'acronym' => $targetAcronym,
                'surname' => $surname,
                'middlename' => $middlename,
                'lastname' => $lastname,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data cleanup migration: rollback is not required.
    }
};
