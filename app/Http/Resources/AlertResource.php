<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Support\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * Expects `listing.branch` and `savedSearches.regions|propertyTypes` to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $matched = $this->savedSearches
            ->map(fn (SavedSearch $search) => SearchCriteria::fromSavedSearch($search)->description())
            ->values();

        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'is_new' => $this->read_at === null,
            // updated_at, not created_at: a refreshed alert is re-announced.
            'alerted_at' => $this->updated_at->toIso8601String(),
            'listing' => new ListingResource($this->listing),
            'is_available' => $this->listing->status === ListingStatus::Live,
            'matched' => $matched,
            // Every search it matched has since been deleted; the UI says so.
            'matched_deleted' => $matched->isEmpty(),
        ];
    }
}
