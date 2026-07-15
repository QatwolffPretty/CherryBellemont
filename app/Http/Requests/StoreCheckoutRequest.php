<?php

namespace App\Http\Requests;

use App\Models\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'delivery_method_id' => ['required', 'integer', 'exists:delivery_methods,id'],
            'delivery_instructions' => ['nullable', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(['duitnow'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = DeliveryMethod::query()->find($this->integer('delivery_method_id'));

            if ($method?->is_pickup) {
                return;
            }

            foreach (['address_line_1', 'city', 'state', 'postcode', 'country'] as $field) {
                if (blank($this->input($field))) {
                    $validator->errors()->add($field, 'This field is required for delivery.');
                }
            }
        });
    }
}
