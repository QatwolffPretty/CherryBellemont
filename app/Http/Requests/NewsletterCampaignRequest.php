<?php

namespace App\Http\Requests;

use App\Models\NewsletterCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsletterCampaignRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'subject' => trim((string) $this->input('subject')),
            'preview_text' => $this->filled('preview_text') ? trim((string) $this->input('preview_text')) : null,
            'cta_text' => $this->filled('cta_text') ? trim((string) $this->input('cta_text')) : null,
            'cta_url' => $this->filled('cta_url') ? trim((string) $this->input('cta_url')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cta_text' => ['nullable', 'string', 'max:80', 'required_with:cta_url'],
            'cta_url' => ['nullable', 'url', 'max:2048', 'required_with:cta_text'],
            'audience_type' => ['required', Rule::in([
                NewsletterCampaign::AUDIENCE_ALL_ACTIVE,
                NewsletterCampaign::AUDIENCE_LAST_30_DAYS,
                NewsletterCampaign::AUDIENCE_LAST_90_DAYS,
            ])],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
