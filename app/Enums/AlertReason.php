<?php

namespace App\Enums;

enum AlertReason: string
{
    case NewListing = 'new_listing';
    case BackOnMarket = 'back_on_market';

    /**
     * Wording shown on an alert card for why the home was flagged.
     */
    public function label(): string
    {
        return match ($this) {
            self::NewListing => 'New listing',
            self::BackOnMarket => 'Back on the market',
        };
    }
}
