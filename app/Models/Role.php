<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'primary_desk',
        'allowed_desks',
        'assigned_modules',
        'is_system_role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allowed_desks' => 'array',
            'assigned_modules' => 'array',
            'is_system_role' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
