<?php

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_requisition_id' => [
                'required',
                'integer',
                Rule::exists('purchase_requisitions', 'id')->where(fn ($q) => $q->whereIn('status', [
                    PurchaseRequisitionStatus::READY_FOR_PO->value,
                    PurchaseRequisitionStatus::PARTIALLY_ORDERED->value,
                ])),
            ],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'terms_and_conditions' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'bill_to_id' => ['required', 'integer', 'exists:addresses,id'],
            'ship_to_id' => ['required', 'integer', 'exists:addresses,id'],
            'payment_terms' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'price_type' => ['required', Rule::enum(PriceType::class)],

            'items' => ['required', 'array', 'min:1'],
            'items.*.line_item_id' => ['required', 'integer', 'distinct', 'exists:line_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'exists:item_units,id'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],

            ...$this->attachmentUploadRules(),
        ];
    }
}
