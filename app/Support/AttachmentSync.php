<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

final class AttachmentSync
{
    /**
     * @param  array<int, UploadedFile>|null  $files
     * @param  array<int>|null  $removedIds
     */
    public static function sync(HasMedia $model, ?array $files, ?array $removedIds): void
    {
        $collection = AttachmentCollection::NAME;

        if (! empty($removedIds)) {
            $model->media()
                ->where('collection_name', $collection)
                ->whereIn('id', $removedIds)
                ->get()
                ->each
                ->delete();
        }

        if ($files === null || $files === []) {
            return;
        }

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $model->addMedia($file)->toMediaCollection($collection);
            }
        }
    }
}
