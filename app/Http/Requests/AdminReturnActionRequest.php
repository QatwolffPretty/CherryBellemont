<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminReturnActionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->is_admin === true; }
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:3000'], 'return_instructions' => ['nullable', 'string', 'max:3000'],
            'passed' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'], 'items.*.approved_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition_received' => ['nullable', 'string', 'max:100'], 'items.*.inspection_notes' => ['nullable', 'string', 'max:3000'],
            'items.*.stock_disposition' => ['nullable', 'in:pending,restocked,damaged,written_off,returned_to_supplier,not_returned'],
            'shipping_refund_amount' => ['nullable', 'numeric', 'min:0'], 'gift_wrap_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_type' => ['nullable', 'in:full,partial'], 'manual_reference' => ['nullable', 'string', 'max:255'],
            'manual_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'replacement_details' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
