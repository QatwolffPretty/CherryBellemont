<?php

namespace App\Services;

/**
 * Provides the one trusted price for Cherry Bellemont's Signature Gift
 * Experience. Keeping the fee in cents avoids browser-provided prices and
 * prevents totals from drifting between checkout and shipping quotes.
 */
class GiftWrapping
{
    public const FEE_CENTS = 3000;

    public function feeCents(bool $selected): int
    {
        return $selected ? self::FEE_CENTS : 0;
    }

    public function fee(bool $selected): string
    {
        return number_format($this->feeCents($selected) / 100, 2, '.', '');
    }
}
