<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HmoCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'policy_code',
        'email',
        'phone',
        'contact_person',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = ['hmo_id'];

    public function getHmoIdAttribute(): string
    {
        return $this->code;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
