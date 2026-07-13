<?php

namespace App\Http\Requests\Workflow;

use App\Enums\WorkflowActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowActionRequest extends FormRequest
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
            'action' => [
                'required',
                Rule::in(array_map(
                    fn (WorkflowActionType $type) => $type->value,
                    WorkflowActionType::actorActions(),
                )),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function action(): WorkflowActionType
    {
        return WorkflowActionType::from($this->validated()['action']);
    }
}
