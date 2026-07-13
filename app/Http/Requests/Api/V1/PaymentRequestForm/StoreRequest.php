<?php

namespace App\Http\Requests\Api\V1\PaymentRequestForm;

use App\Enums\PaymentRequestFormStatus;
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'company_profile_id' => ['nullable', 'integer', 'exists:company_profiles,id'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'stamp_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([PaymentRequestFormStatus::DRAFT->value])],

            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', 'exists:departments,id'],

            'notes' => ['nullable', 'array'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],

            ...$this->attachmentUploadRules(),
        ];
    }
}
