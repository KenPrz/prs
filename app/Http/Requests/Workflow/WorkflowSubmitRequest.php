<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Subject-level authorization is handled in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
