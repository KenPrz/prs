<?php

namespace App\Http\Requests\ReceivingReport;

use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\ReceivingReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceivingReportRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ReceivingReport::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
            'items.*.quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],

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
            'purchase_order_id.required' => 'A purchase order is required.',
            'purchase_order_id.exists' => 'The selected purchase order does not exist.',
            'received_date.required' => 'The received date is required.',

            'items.required' => 'At least one item is required.',
            'items.*.purchase_order_item_id.required' => 'A PO item reference is required for item #:position.',
            'items.*.purchase_order_item_id.exists' => 'The selected PO item for item #:position does not exist.',
            'items.*.purchase_order_item_id.distinct' => 'Item #:position references the same PO item as another row. Combine them into one row.',
            'items.*.quantity_received.required' => 'The received quantity for item #:position is required.',
            'items.*.quantity_received.min' => 'The received quantity for item #:position must be at least :min.',
        ];
    }
}
