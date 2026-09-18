<?php

namespace App\Enums;

enum HmoAuthStatus: string
{
    case PENDING = 'Pending Approval';
    case APPROVED = 'Approved';
    case DECLINED = 'Declined';
    case REROUTED = 'Rerouted to Cashdesk';

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isDeclined(): bool
    {
        return $this === self::DECLINED;
    }

    public function isRerouted(): bool
    {
        return $this === self::REROUTED;
    }
}
