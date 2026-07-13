<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class AttachmentRequestFiles
{
    /**
     * @return list<UploadedFile>|null
     */
    public static function normalize(Request $request): ?array
    {
        $files = $request->file('attachments');
        if ($files === null) {
            return null;
        }
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter($files, fn ($f): bool => $f instanceof UploadedFile));
    }
}
