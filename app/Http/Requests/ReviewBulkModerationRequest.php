<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBulkModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'review_ids' => ['required', 'array', 'min:1'],
            'review_ids.*' => ['integer', 'distinct'],
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ];
    }
}
