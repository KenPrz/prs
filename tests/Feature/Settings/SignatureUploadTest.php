<?php

use App\Models\User;
use App\Support\AttachmentCollection;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;

/*
|--------------------------------------------------------------------------
| Signature uploads
|--------------------------------------------------------------------------
| Signatures are stamped onto legal documents, so uploads are strict:
| PNG only, 2 MB max. Seeded demo users must get working signatures in the
| attachments collection (the one the whole system reads).
*/

test('a valid png signature uploads and becomes active', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signatures.store'), [
            'signature' => UploadedFile::fake()->image('sig.png', 400, 150),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signature = $user->signatures()->first();
    expect($signature->is_active)->toBeTrue()
        ->and($signature->getFirstMedia(AttachmentCollection::NAME))->not->toBeNull();
});

test('a non-png signature is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signatures.store'), [
            'signature' => UploadedFile::fake()->image('sig.jpg', 400, 150),
        ])
        ->assertSessionHasErrors('signature');

    expect($user->signatures()->count())->toBe(0);
});

test('an oversized signature is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signatures.store'), [
            'signature' => UploadedFile::fake()->create('sig.png', 3000, 'image/png'), // 3 MB
        ])
        ->assertSessionHasErrors('signature');

    expect($user->signatures()->count())->toBe(0);
});

test('seeded demo users receive active signatures the system can actually read', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $seeded = User::whereHas('signatures')->get();
    expect($seeded)->not->toBeEmpty();

    foreach ($seeded as $user) {
        $media = $user->activeSignature?->getFirstMedia(AttachmentCollection::NAME);
        expect($media)->not->toBeNull()
            ->and($media->mime_type)->toBe('image/png');
    }
});
