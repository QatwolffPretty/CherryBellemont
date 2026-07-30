<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendOrderEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'notification_type' => ['required', Rule::in(['order_placed', 'latest_status', 'shipping', 'refund'])],
        ];
    }
}
