<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case UnderOffer = 'under_offer';
    case Sold = 'sold';

    /**
     * Human-readable status for the UI; the stored value stays the snake_case case value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
            self::UnderOffer => 'Under offer',
            self::Sold => 'Sold',
        };
    }
}
