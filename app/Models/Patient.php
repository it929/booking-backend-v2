<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'mrn',
        'name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'hmo_company_id',
        'hmo_policy_code',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected $appends = [
        'patient_type',
        'is_hmo',
        'is_private',
        'hmo_name',
    ];

    public function getPatientTypeAttribute(): string
    {
        return $this->hmo_company_id !== null ? 'HMO' : 'Private';
    }

    public function getIsHmoAttribute(): bool
    {
        return $this->patient_type === 'HMO';
    }

    public function getIsPrivateAttribute(): bool
    {
        return $this->patient_type === 'Private';
    }

    public function getHmoNameAttribute(): string
    {
        return $this->hmoCompany ? $this->hmoCompany->name : 'N/A';
    }

    public function scopePatientType($query, string $type)
    {
        $normalized = strtolower(trim($type));
        if ($normalized === 'hmo') {
            return $query->whereNotNull('hmo_company_id');
        } elseif ($normalized === 'private' || $normalized === 'self-pay') {
            return $query->whereNull('hmo_company_id');
        }
        return $query;
    }

    public function hmoCompany(): BelongsTo
    {
        return $this->belongsTo(HmoCompany::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
