<?php

namespace App\Support;

use Spatie\MediaLibrary\HasMedia;

final class AttachmentResource
{
    /**
     * @return list<array{id: int, name: string, size: int, mime_type: string|null, download_url: string}>
     */
    public static function toArray(HasMedia $model): array
    {
        return $model->getMedia(AttachmentCollection::NAME)->map(fn ($media) => [
            'id' => $media->id,
            'name' => $media->name,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'download_url' => route('attachments.download', $media),
        ])->values()->all();
    }
}
