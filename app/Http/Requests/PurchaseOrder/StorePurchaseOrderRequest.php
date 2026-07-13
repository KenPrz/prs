<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_requisition_id' => [
                'required',
                'integer',
                'exists:purchase_requisitions,id',
                Rule::exists('purchase_requisitions', 'id')->where(function ($query) {
                    $query->whereIn('status', [
                        PurchaseRequisitionStatus::READY_FOR_PO,
                        PurchaseRequisitionStatus::PARTIALLY_ORDERED,
                    ]);
                }),
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
            'items.*.price' => ['required', 'numeric', 'min:0'],

            'notes' => ['nullable', 'array'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_requisition_id.required' => 'A purchase requisition is required.',
            'purchase_requisition_id.exists' => 'The purchase requisition must be marked ready for PO before creating a purchase order.',
            'supplier_id.required' => 'A supplier is required.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'bill_to_id.required' => 'A billing address is required.',
            'ship_to_id.required' => 'A shipping address is required.',
            'payment_terms.required' => 'Payment terms are required.',
            'currency.required' => 'A currency is required.',

            'items.required' => 'At least one item is required.',
            'items.*.line_item_id.required' => 'A PR line item reference is required for item #:position.',
            'items.*.line_item_id.exists' => 'The selected PR line item for item #:position does not exist.',
            'items.*.line_item_id.distinct' => 'Item #:position references the same PR line item as another row. Combine them into one row.',
            'items.*.quantity.required' => 'The quantity for item #:position is required.',
            'items.*.quantity.min' => 'The quantity for item #:position must be at least :min.',
            'items.*.unit_id.required' => 'The unit for item #:position is required.',
            'items.*.unit_id.exists' => 'The selected unit for item #:position is invalid.',
            'items.*.price.required' => 'The price for item #:position is required.',
            'items.*.price.numeric' => 'The price for item #:position must be a number.',

            'price_type.required' => 'A price type is required.',
        ];
    }
}
