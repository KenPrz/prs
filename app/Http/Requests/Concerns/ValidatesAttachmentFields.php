<?php

namespace App\Http\Requests\Concerns;

use App\Support\AttachmentCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesAttachmentFields
{
    /**
     * @return array<string, array<int, string|Exists>>
     */
    protected function attachmentUploadRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp'],
        ];
    }

    /**
     * @return array<string, array<int, string|Exists>>
     */
    protected function removedAttachmentRules(string $modelClass, string $routeParameter): array
    {
        return [
            'removed_attachment_ids' => ['nullable', 'array'],
            'removed_attachment_ids.*' => [
                'integer',
                Rule::exists('media', 'id')->where(function ($query) use ($modelClass, $routeParameter): void {
                    $model = $this->route($routeParameter);
                    $query->where('model_type', $modelClass)
                        ->where('model_id', $model->id)
                        ->where('collection_name', AttachmentCollection::NAME);
                }),
            ],
        ];
    }
}
