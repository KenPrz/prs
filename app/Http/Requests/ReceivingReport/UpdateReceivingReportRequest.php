<?php

namespace App\Http\Requests\ReceivingReport;

use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\ReceivingReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReceivingReportRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('receiving_report'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'received_date' => ['sometimes', 'date'],
            'remarks' => ['nullable', 'string'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:receiving_report_items,id'],
            'items.*.purchase_order_item_id' => ['required_with:items', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],

            'notes' => ['nullable', 'array'],
            'notes.*.id' => ['nullable', 'integer', 'exists:notes,id'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
            ...$this->removedAttachmentRules(ReceivingReport::class, 'receiving_report'),
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
            'items.*.purchase_order_item_id.required_with' => 'A PO item reference is required for item #:position.',
            'items.*.purchase_order_item_id.exists' => 'The selected PO item for item #:position does not exist.',
            'items.*.purchase_order_item_id.distinct' => 'Item #:position references the same PO item as another row. Combine them into one row.',
            'items.*.quantity_received.required_with' => 'The received quantity for item #:position is required.',
            'items.*.quantity_received.min' => 'The received quantity for item #:position must be at least :min.',
        ];
    }
}
