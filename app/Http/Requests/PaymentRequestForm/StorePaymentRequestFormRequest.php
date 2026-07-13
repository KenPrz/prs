<?php

namespace App\Http\Requests\PaymentRequestForm;

use App\Enums\PaymentRequestFormStatus;
use App\Http\Requests\Concerns\ValidatesAttachmentFields;
use App\Models\PaymentRequestForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequestFormRequest extends FormRequest
{
    use ValidatesAttachmentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', PaymentRequestForm::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
            'status' => [
                'nullable',
                Rule::in([PaymentRequestFormStatus::DRAFT->value]),
            ],

            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', 'exists:departments,id'],

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
            'supplier_id.required' => 'A payee / supplier is required.',
            'supplier_id.exists' => 'The selected payee / supplier is invalid.',
            'amount.required' => 'The billing amount is required.',
            'amount.min' => 'The billing amount must be at least :min.',
            'department_ids.required' => 'At least one department is required.',
            'department_ids.*.exists' => 'The selected department is invalid.',
            'notes.*.content.max' => 'The note on row #:position cannot exceed 1000 characters.',
            'status.in' => 'Submit for approval after saving a draft using the submit action instead of setting status here.',
        ];
    }
}
