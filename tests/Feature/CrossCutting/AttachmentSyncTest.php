<?php

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Support\AttachmentCollection;
use App\Support\AttachmentSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// X-02 attachments can be added and removed via AttachmentSync
test('attachment sync adds and removes files on a document', function () {
    Storage::fake('local');

    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create();

    AttachmentSync::sync($pr, [UploadedFile::fake()->create('quote.pdf', 12)], null);
    expect($pr->fresh()->getMedia(AttachmentCollection::NAME))->toHaveCount(1);

    $mediaId = $pr->fresh()->getMedia(AttachmentCollection::NAME)->first()->id;
    AttachmentSync::sync($pr, null, [$mediaId]);
    expect($pr->fresh()->getMedia(AttachmentCollection::NAME))->toHaveCount(0);
});
