<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ListingIndexRequest extends SearchCriteriaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * A GET page cannot send the user "back" to the URL that just failed, or it
     * would loop. Redirect to the same page without the bad filters instead; the
     * error is flashed and shown there, on a page that works.
     */
    protected function failedValidation(Validator $validator): void
    {
        $bad = array_map(fn (string $key) => explode('.', $key)[0], $validator->errors()->keys());

        throw (new ValidationException($validator))
            ->redirectTo(route('listings.index', Arr::except($this->query(), $bad)));
    }
}
