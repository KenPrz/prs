<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', 'exists:workflow_assignments,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
