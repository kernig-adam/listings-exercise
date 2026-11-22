<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Support\SearchCriteria;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The criteria rules, written once for the listings page and for saving a
 * search so the two can never accept different things.
 */
abstract class SearchCriteriaRequest extends FormRequest
{
    /**
     * Anyone may use this form; the route middleware decides who gets in.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Multi values arrive as "a,b", as region[]=a, or as one old-style value.
     * They become a clean list (blanks dropped) and regions take the branch's
     * own casing, so everything downstream compares like with like. Unknown
     * values are left as given for validation to reject.
     */
    protected function prepareForValidation(): void
    {
        $regions = collect(Branch::regions())->keyBy(fn (string $region) => mb_strtolower($region));

        $this->merge([
            'region' => array_map(
                fn (mixed $region) => is_string($region) ? ($regions[mb_strtolower($region)] ?? $region) : $region,
                $this->list('region'),
            ) ?: null,
            'property_type' => $this->list('property_type') ?: null,
        ]);
    }

    /**
     * Turns "a,b", an array or a single value into a plain list, dropping blanks.
     *
     * @return list<mixed>
     */
    private function list(string $key): array
    {
        $items = [];

        foreach (Arr::wrap($this->input($key)) as $item) {
            if (! is_string($item)) {
                $items[] = $item; // not a string: left for validation to reject

                continue;
            }

            foreach (explode(',', $item) as $part) {
                if (trim($part) !== '') {
                    $items[] = trim($part);
                }
            }
        }

        return $items;
    }

    /**
     * Region and type are checked at the list level, not per item, so a bad
     * value is reported on `region` / `property_type` where the form field is.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'min_price' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_PRICE, ...$this->notAbove('max_price')],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_PRICE],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_BEDROOMS, ...$this->notAbove('max_bedrooms')],
            'max_bedrooms' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_BEDROOMS],
            'min_bathrooms' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_BATHROOMS, ...$this->notAbove('max_bathrooms')],
            'max_bathrooms' => ['nullable', 'integer', 'min:0', 'max:'.SearchCriteria::MAX_BATHROOMS],
            'region' => ['bail', 'nullable', 'array', 'max:50', $this->allKnown(
                Branch::regions(),
                'That region is not one we cover. Choose from the list.',
            )],
            'property_type' => ['bail', 'nullable', 'array', 'max:'.count(PropertyType::cases()), $this->allKnown(
                array_map(fn (PropertyType $type) => $type->value, PropertyType::cases()),
                'That property type is not available. Choose from the list.',
            )],
        ];
    }

    /**
     * Friendlier error messages for a minimum that is higher than its maximum.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'min_price.lte' => 'The minimum price cannot be higher than the maximum.',
            'min_bedrooms.lte' => 'The minimum bedrooms cannot be more than the maximum.',
            'min_bathrooms.lte' => 'The minimum bathrooms cannot be more than the maximum.',
        ];
    }

    /**
     * Only add the cross-field rule when there is a maximum to compare with.
     *
     * @return list<string>
     */
    private function notAbove(string $max): array
    {
        return $this->filled($max) ? ["lte:{$max}"] : [];
    }

    /**
     * A rule that fails if any item is not one of the allowed values.
     * Gives one error per field, however many items are bad.
     *
     * @param  list<string>  $known
     */
    private function allKnown(array $known, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($known, $message): void {
            foreach ($value as $item) {
                if (! is_string($item) || ! in_array($item, $known, true)) {
                    $fail($message);

                    return;
                }
            }
        };
    }

    /**
     * The validated input as a SearchCriteria, the one shape for "a search" across the app.
     * Only call after validation has passed.
     */
    public function criteria(): SearchCriteria
    {
        return SearchCriteria::fromInput($this->validated());
    }
}
