<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertsReadRequest extends FormRequest
{
    /**
     * Ownership is not decided here: the controller loads the ids through the
     * user's alerts and answers 404 for any that are not theirs.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `read` only accepts true: marking unread is not supported, and a field
     * that can only say yes keeps the verb honest if more changes arrive later.
     * The cap matches one page of cards.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'alert_ids' => ['required', 'array', 'min:1', 'max:15'],
            'alert_ids.*' => ['integer', 'min:1', 'distinct'],
            'read' => ['required', 'accepted'],
        ];
    }

    /**
     * @return list<int>
     */
    public function alertIds(): array
    {
        return array_map('intval', $this->validated('alert_ids'));
    }
}
