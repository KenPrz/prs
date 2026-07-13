<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkflowCancelRequest extends FormRequest
{
    /**
     * Authorization is enforced by the controller via the subject's policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Cancelling a workflow always requires a reason.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:1000'],
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
            'comment.required' => 'A cancellation reason is required.',
        ];
    }
}
