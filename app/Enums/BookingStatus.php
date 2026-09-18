<?php

namespace App\Enums;

enum BookingStatus: string
{
    case CONFIRMED = 'Confirmed';
    case PAYMENT_APPROVED = 'Payment Approved';
    case HMO_APPROVED = 'HMO Approved';
    case CHECKED_IN = 'Checked In';
    case IN_CONSULTATION = 'In Consultation';
    case COMPLETED = 'Completed';
    case CANCELLED = 'Cancelled';
    case REJECTED = 'Rejected';
    case DELETED = 'Deleted';

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Confirmed',
            self::PAYMENT_APPROVED => 'Payment Approved',
            self::HMO_APPROVED => 'HMO Approved',
            self::CHECKED_IN => 'Checked In',
            self::IN_CONSULTATION => 'In Consultation',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REJECTED => 'Rejected',
            self::DELETED => 'Deleted',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::REJECTED, self::DELETED], true);
    }

    public function countsTowardCapacity(): bool
    {
        return !in_array($this, [self::CANCELLED, self::REJECTED, self::DELETED], true);
    }
}
