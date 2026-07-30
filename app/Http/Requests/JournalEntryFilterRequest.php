<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryFilterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'journal_number' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'reversed', 'cancelled'])],
            'description' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'max:60'],
            'posted_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->validated();
    }
}
