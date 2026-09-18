<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = ['slot_id'];

    public function getSlotIdAttribute(): string
    {
        return $this->code;
    }
}
