<?php

namespace App\Concerns;

use App\Support\AttachmentCollection;

trait RegistersAttachmentMediaCollection
{
    public const ATTACHMENT_COLLECTION = AttachmentCollection::NAME;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(AttachmentCollection::NAME);
    }
}
