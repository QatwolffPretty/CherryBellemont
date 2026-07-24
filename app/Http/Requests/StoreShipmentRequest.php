<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'courier_id' => ['nullable', 'integer', 'exists:couriers,id'],
            'service_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'estimated_delivery_at' => ['nullable', 'date', 'after_or_equal:today'],
            'label' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
