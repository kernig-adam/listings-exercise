<?php

namespace App\Http\Requests;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Validation\Validator;

class SavedSearchStoreRequest extends SearchCriteriaRequest
{
    /**
     * Same 401 guard as Controller::currentUser(); see the note there.
     */
    public function currentUser(): User
    {
        $user = $this->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * The rules that need the whole search (or the user's other searches) run
     * only once the fields themselves are valid. They share the error key
     * `criteria` so the UI shows them in one place.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $criteria = $this->criteria();
            $user = $this->currentUser();

            if ($criteria->isEmpty()) {
                // An empty search would alert on every new listing: spam by design.
                $validator->errors()->add('criteria', 'Choose at least one filter before saving a search.');
            } elseif ($criteria->isSavedBy($user)) {
                $validator->errors()->add('criteria', 'You already have this search saved.');
            } elseif ($user->savedSearches()->count() >= SavedSearch::MAX_PER_USER) {
                $validator->errors()->add('criteria', 'You have '.SavedSearch::MAX_PER_USER.' saved searches. Delete one to save another.');
            }
        }];
    }
}
