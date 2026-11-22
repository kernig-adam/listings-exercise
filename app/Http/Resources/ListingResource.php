<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Listing
 */
class ListingResource extends JsonResource
{
    /** Placeholder photos in public/images/listings (house-1.webp … house-5.webp). */
    private const PLACEHOLDER_IMAGES = 5;

    /**
     * The listing details sent to the page. Enums go out as a value plus a label.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Derived from the id so a home always shows the same photo.
        $image = $this->id % self::PLACEHOLDER_IMAGES + 1;

        return [
            'id' => $this->id,
            'image_url' => asset("images/listings/house-{$image}.webp"),
            'reference' => $this->reference,
            'address_line_1' => $this->address_line_1,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'price' => $this->price,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'property_type' => $this->property_type->value,
            'property_type_label' => $this->property_type->label(),
            'status' => $this->status->value,
            'listed_at' => $this->listed_at?->toIso8601String(),
            'branch' => new BranchResource($this->branch),
        ];
    }
}
