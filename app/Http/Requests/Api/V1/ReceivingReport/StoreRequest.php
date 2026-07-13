<?php

namespace App\Http\Requests\Api\V1\ReceivingReport;

use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use Illuminate\Foundation\Http\FormRequest;

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
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
            'items.*.quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],

            ...$this->attachmentUploadRules(),
        ];
    }
}
