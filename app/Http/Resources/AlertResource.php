<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Support\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * Expects `listing.branch` and `savedSearches.regions|propertyTypes` to be eager
     * loaded, and the `history` aggregate (first_alerted_at, alert_count, unread_count)
     * set by AlertController.
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
            // Any unread alert for the home, as it was before this view marked them read.
            'is_new' => $this->history->unread_count > 0,
            'first_alerted_at' => Carbon::parse($this->history->first_alerted_at)->toIso8601String(),
            'alert_count' => (int) $this->history->alert_count,
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
