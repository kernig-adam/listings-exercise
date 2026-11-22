<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertsReadRequest extends FormRequest
{
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
            'alert_ids.*' => ['integer', 'distinct'],
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
