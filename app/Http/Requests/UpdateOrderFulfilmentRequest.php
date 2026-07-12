<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class UpdateOrderFulfilmentRequest extends FormRequest { public function authorize(): bool{return $this->user()?->is_admin===true;} public function rules(): array{return ['order_status'=>['required',Rule::in(['pending','payment_review','paid','processing','packed','shipped','delivered','cancelled'])],'courier_name'=>['nullable','string','max:120','required_if:order_status,shipped'],'tracking_number'=>['nullable','string','max:160','required_if:order_status,shipped'],'cancellation_reason'=>['nullable','string','max:2000','required_if:order_status,cancelled'],'admin_notes'=>['nullable','string','max:5000']];} }
