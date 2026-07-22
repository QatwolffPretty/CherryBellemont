<?php

namespace App\Http\Requests;

use App\Models\ProductStockNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminProductStockNotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                ProductStockNotification::STATUS_WAITING,
                ProductStockNotification::STATUS_NOTIFIED,
                ProductStockNotification::STATUS_CANCELLED,
            ])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
