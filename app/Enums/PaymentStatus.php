<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'Pending';
    case PAID = 'Paid';
    case COVERED_BY_HMO = 'Covered by HMO';
    case WAIVED = 'Waived';
    case REFUNDED = 'Refunded';

    public function isSettled(): bool
    {
        return in_array($this, [self::PAID, self::COVERED_BY_HMO, self::WAIVED], true);
    }
}
