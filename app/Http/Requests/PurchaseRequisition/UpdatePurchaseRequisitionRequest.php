<?php

namespace App\Http\Requests\PurchaseRequisition;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\PurposeType;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PurchaseRequisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequisitionRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var PurchaseRequisition $requisition */
        $requisition = $this->route('purchase_requisition');

        return $this->user()->can('update', $requisition);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'status' => [
                'required',
                Rule::in([PurchaseRequisitionStatus::DRAFT->value]),
            ],

            'price_type' => ['required', Rule::enum(PriceType::class)],
            'purpose_type' => ['required', Rule::enum(PurposeType::class)],
            'expected_useful_life' => ['nullable', 'string', 'max:255'],
            'to_be_ordered_by_id' => ['nullable', 'integer', 'exists:users,id'],

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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_ids.required' => 'At least one requesting department is required.',
            'department_ids.*.exists' => 'The selected department is invalid.',

            'line_items.required' => 'At least one line item is required.',
            'line_items.*.name.required' => 'The description for item #:position is required.',
            'line_items.*.name.max' => 'The description for item #:position cannot exceed 255 characters.',
            'line_items.*.quantity.required' => 'The quantity for item #:position is required.',
            'line_items.*.quantity.min' => 'The quantity for item #:position must be at least :min.',
            'line_items.*.unit_id.required' => 'The unit for item #:position is required.',
            'line_items.*.unit_id.exists' => 'The selected unit for item #:position is invalid.',
            'line_items.*.price.numeric' => 'The price for item #:position must be a number.',
            'line_items.*.price.min' => 'The price for item #:position must be at least :min.',

            'price_type.required' => 'A price type is required.',
            'purpose_type.required' => 'A purpose type is required.',

            'notes.*.content.max' => 'The note on row #:position cannot exceed 1000 characters.',

            'status.in' => 'Submit for approval using the submit action instead of changing status here.',
        ];
    }
}
