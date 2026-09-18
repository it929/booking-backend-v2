<?php

namespace App\Models;

use App\Enums\BillingType;
use App\Enums\BookingStatus;
use App\Enums\HmoAuthStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'patient_id',
        'doctor_id',
        'department_id',
        'hmo_company_id',
        'appointment_date',
        'appointment_time',
        'patient_name',
        'patient_phone',
        'patient_email',
        'reason',
        'payment_type',
        'hmo_policy_code',
        'hmo_auth_code',
        'hmo_status',
        'referral_doc_name',
        'referral_doc_data',
        'referral_doc_text',
        'payment_status',
        'payment_method',
        'invoice_ref',
        'status',
        'is_active',
        'delete_reason',
        'checked_in_at',
        'completed_at',
        'reminder_sent_at',
        'reminder_sms_status',
        'reminder_email_status',
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'status' => BookingStatus::class,
        'hmo_status' => HmoAuthStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    protected $appends = [
        'ref_code',
        'date',
        'time',
        'doctor_name',
        'doctor_initial_name',
        'doctor_specialty',
        'hmo_name',
        'counts_toward_capacity',
        'patient_type',
        'billing_classification',
        'is_hmo',
        'is_private',
    ];

    public function getDoctorInitialNameAttribute(): string
    {
        if ($this->relationLoaded('doctor') && $this->doctor) {
            return $this->doctor->initial_name;
        }
        if ($this->doctor_id) {
            $doc = Doctor::find($this->doctor_id);
            if ($doc) {
                return $doc->initial_name;
            }
        }
        $raw = $this->doctor_name ?: '';
        $clean = preg_replace('/\b(dr|doctor|prof|professor|mr|mrs|ms|nurse|pharm)\.?\b/i', '', $raw);
        $parts = preg_split('/[\s\-_.]+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($parts as $part) {
            if (preg_match('/[a-zA-Z]/', $part, $matches)) {
                $initials .= strtoupper($matches[0]);
            }
        }
        return 'Dr. ' . ($initials ?: 'DOC');
    }

    public function getBillingClassificationAttribute(): string
    {
        return BillingType::fromInput($this->payment_type ?? '')->value;
    }

    public function getPatientTypeAttribute(): string
    {
        if ($this->hmo_company_id !== null || (isset($this->attributes['payment_type']) && stripos($this->attributes['payment_type'], 'hmo') !== false)) {
            return 'HMO';
        }
        return 'Private';
    }

    public function getIsHmoAttribute(): bool
    {
        return $this->patient_type === 'HMO';
    }

    public function getIsPrivateAttribute(): bool
    {
        return $this->patient_type === 'Private';
    }

    public function isCompleted(): bool
    {
        return $this->status === BookingStatus::COMPLETED || $this->status?->value === 'Completed';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID || $this->payment_status?->value === 'Paid';
    }

    public function isHmoApproved(): bool
    {
        return $this->hmo_status === HmoAuthStatus::APPROVED || $this->hmo_status?->value === 'Approved';
    }

    public function scopePatientType($query, string $type)
    {
        $normalized = strtolower(trim($type));
        if ($normalized === 'hmo') {
            return $query->where(function ($q) {
                $q->whereNotNull('hmo_company_id')
                  ->orWhere('payment_type', 'like', '%hmo%');
            });
        } elseif ($normalized === 'private' || $normalized === 'self-pay') {
            return $query->where(function ($q) {
                $q->whereNull('hmo_company_id')
                  ->where('payment_type', 'not like', '%hmo%');
            });
        }
        return $query;
    }

    public function scopeStatus($query, BookingStatus|string $status)
    {
        $val = $status instanceof BookingStatus ? $status->value : $status;
        return $query->where('status', $val);
    }

    public function getRefCodeAttribute(): string
    {
        return $this->reference_code ?? '';
    }

    public function getDateAttribute(): ?string
    {
        return $this->appointment_date ? $this->appointment_date->format('Y-m-d') : null;
    }

    public function getTimeAttribute(): string
    {
        return $this->appointment_time ?? '';
    }

    public function getDoctorNameAttribute(): string
    {
        if ($this->doctor) {
            return $this->doctor->full_name ?: $this->doctor->name;
        }
        return 'Specialist Doctor';
    }

    public function getDoctorSpecialtyAttribute(): string
    {
        if ($this->department) {
            return $this->department->name;
        }
        if ($this->doctor && $this->doctor->department) {
            return $this->doctor->department->name;
        }
        return 'General Medicine';
    }

    public function getHmoNameAttribute(): string
    {
        return $this->hmoCompany ? $this->hmoCompany->name : 'N/A';
    }

    public function getCountsTowardCapacityAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->status instanceof BookingStatus) {
            return $this->status->countsTowardCapacity();
        }
        $excluded = ['cancelled', 'canceled', 'rejected', 'declined', 'deleted', 'expired', 'void'];
        return !in_array(strtolower(trim((string) $this->status)), $excluded, true);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hmoCompany(): BelongsTo
    {
        return $this->belongsTo(HmoCompany::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
