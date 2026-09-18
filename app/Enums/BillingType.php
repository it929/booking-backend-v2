<?php

namespace App\Enums;

enum BillingType: string
{
    case PRIVATE = 'Private Self-Pay';
    case HMO = 'HMO Insurance';
    case CORPORATE = 'Corporate';

    public function isHmo(): bool
    {
        return $this === self::HMO;
    }

    public function isPrivate(): bool
    {
        return $this === self::PRIVATE;
    }

    public static function fromInput(?string $value): self
    {
        if (!$value) {
            return self::PRIVATE;
        }

        $lower = strtolower(trim($value));
        if (str_contains($lower, 'hmo')) {
            return self::HMO;
        }
        if (str_contains($lower, 'corporate') || str_contains($lower, 'retainer')) {
            return self::CORPORATE;
        }

        return self::PRIVATE;
    }
}
