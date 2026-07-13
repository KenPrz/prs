<?php

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Enums\PriceType;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    public function authorize(): bool
    {
        return true;
    }

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
            'items.*.price' => ['nullable', 'numeric', 'min:0'],

            ...$this->attachmentUploadRules(),
            ...$this->removedAttachmentRules(PurchaseOrder::class, 'purchase_order'),
        ];
    }
}
