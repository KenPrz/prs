<?php

namespace App\Http\Requests\Api\V1\PurchaseRequisition;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PurchaseRequisition;
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::in([PurchaseRequisitionStatus::DRAFT->value])],

            'price_type' => ['required', Rule::enum(PriceType::class)],

            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', 'exists:departments,id'],

            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.id' => ['nullable', 'integer', 'exists:line_items,id'],
            'line_items.*.name' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_id' => ['required', 'integer', 'exists:item_units,id'],
            'line_items.*.price' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'array'],
            'notes.*.id' => ['nullable', 'integer', 'exists:notes,id'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
            ...$this->removedAttachmentRules(PurchaseRequisition::class, 'purchase_requisition'),
        ];
    }
}
