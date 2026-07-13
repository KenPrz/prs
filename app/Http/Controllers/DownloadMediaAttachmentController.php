<?php

namespace App\Http\Controllers;

use App\Support\AttachmentCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadMediaAttachmentController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        $model = $media->model;

        if ($model === null || $media->collection_name !== AttachmentCollection::NAME) {
            abort(404);
        }

        $this->authorize('view', $model);

        return response()->download($media->getPath(), $media->file_name);
    }
}
