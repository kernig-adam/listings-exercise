<?php

namespace App\Enums;

enum AlertReason: string
{
    case NewListing = 'new_listing';
    case BackOnMarket = 'back_on_market';

    public function label(): string
    {
        return match ($this) {
            self::NewListing => 'New listing',
            self::BackOnMarket => 'Back on the market',
        };
    }
}
