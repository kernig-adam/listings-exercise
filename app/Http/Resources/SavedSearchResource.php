<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use App\Support\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * Expects `regions` and `propertyTypes` to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $criteria = SearchCriteria::fromSavedSearch($this->resource);

        return [
            'id' => $this->id,
            'criteria' => $criteria->toArray(),
            'description' => $criteria->description(),
            // Built here so Vue never re-implements how filters map to the URL.
            'view_url' => route('listings.index', $criteria->toQuery()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
