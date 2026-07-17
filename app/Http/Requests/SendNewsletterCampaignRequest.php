<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendNewsletterCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['confirm_send' => ['accepted']];
    }
}
