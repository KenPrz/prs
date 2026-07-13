<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PriceType;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'terms_and_conditions' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'bill_to_id' => ['sometimes', 'integer', 'exists:addresses,id'],
            'ship_to_id' => ['sometimes', 'integer', 'exists:addresses,id'],
            'payment_terms' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'price_type' => ['sometimes', Rule::enum(PriceType::class)],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.line_item_id' => ['required_with:items', 'integer', 'distinct', 'exists:line_items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_id' => ['required_with:items', 'integer', 'exists:item_units,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],

            'notes' => ['nullable', 'array'],
            'notes.*.id' => ['nullable', 'integer', 'exists:notes,id'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
            ...$this->removedAttachmentRules(PurchaseOrder::class, 'purchase_order'),
        ];
    }
}
