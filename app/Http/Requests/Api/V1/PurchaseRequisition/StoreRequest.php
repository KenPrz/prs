<?php

namespace App\Http\Requests\Api\V1\PurchaseRequisition;

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'status' => ['nullable', Rule::in([PurchaseRequisitionStatus::DRAFT->value])],

            'price_type' => ['required', Rule::enum(PriceType::class)],

            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', 'exists:departments,id'],

            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.name' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_id' => ['required', 'integer', 'exists:item_units,id'],
            'line_items.*.price' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'array'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'department_ids.required' => 'At least one requesting department is required.',
            'department_ids.*.exists' => 'The selected department is invalid.',
            'line_items.required' => 'At least one line item is required.',
            'line_items.*.name.required' => 'The description for item #:position is required.',
            'line_items.*.quantity.required' => 'The quantity for item #:position is required.',
            'line_items.*.quantity.min' => 'The quantity for item #:position must be at least :min.',
            'line_items.*.unit_id.required' => 'The unit for item #:position is required.',
            'price_type.required' => 'A price type is required.',
        ];
    }
}
