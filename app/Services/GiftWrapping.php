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

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('gift.enabled', true);
    }

    public function title(): string
    {
        return (string) $this->settings->get('gift.title', 'Cherry Bellemont Signature Gift Experience');
    }

    public function description(): string
    {
        return (string) $this->settings->get('gift.description', 'Your order will be presented in Cherry Bellemont signature wrapping with premium tissue, ribbon, and a personalised gift card.');
    }

    public function messageMaxLength(): int
    {
        return max(1, (int) $this->settings->get('gift.message_max_length', 250));
    }

    public function feeCents(bool $selected): int
    {
        if (! $selected || ! $this->enabled()) {
            return 0;
        }

        return (int) round(max(0, (float) $this->settings->get('gift.wrap_price', '30.00')) * 100);
    }

    public function fee(bool $selected): string
    {
        return number_format($this->feeCents($selected) / 100, 2, '.', '');
    }
}
