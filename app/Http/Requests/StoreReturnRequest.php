<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\SettingsService;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $settings = app(SettingsService::class);
        $maximumImages = max(1, (int) $settings->get('returns.maximum_images', config('store.returns.maximum_return_images', 5)));
        $maximumImageSizeMb = max(1, (int) $settings->get('returns.maximum_image_size_mb', config('store.returns.maximum_return_image_size_mb', 5)));

        return [
            'request_type' => ['required', Rule::in(['return', 'refund', 'exchange'])],
            'preferred_resolution' => ['nullable', Rule::in(['refund', 'exchange', 'replacement', 'store_review_required'])],
            'customer_details' => ['nullable', 'string', 'max:3000'],
            'policy_acknowledged' => ['accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', Rule::in(['damaged_item', 'defective_item', 'incorrect_item', 'missing_item', 'materially_different', 'size_or_suitability', 'other'])],
            'images' => ['nullable', 'array', 'max:'.$maximumImages],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.($maximumImageSizeMb * 1024)],
        ];
    }
}
