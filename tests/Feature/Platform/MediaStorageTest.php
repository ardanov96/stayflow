<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\MediaStorage;

it('never serves a private file through a public URL', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    config([
        'media.private_disk' => 'local',
        'media.public_disk' => 'public',
        'media.public_url' => 'https://cdn.example.test',
    ]);

    $storage = app(MediaStorage::class);

    $path = $storage->putPrivate('invoices/2026/INV-1.pdf', '%PDF-1.4 the invoice of the guest');

    expect($path)->toBe('invoices/2026/INV-1.pdf');

    // The file is on the private disk, and on no other.
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);

    // The URL is signed and it expires.
    $url = $storage->privateUrl($path, 15);

    expect($url)->toContain('signature=')
        ->and($url)->toContain('expires=');

    // Without a valid signature the file is not reachable.
    $this->get('/media/private/'.$path)->assertForbidden();

    // With it, the file comes out.
    $this->get($url)
        ->assertOk()
        ->assertSee('the invoice of the guest', false);
});

it('serves a public file from the CDN', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    config([
        'media.private_disk' => 'local',
        'media.public_disk' => 'public',
        'media.public_url' => 'https://cdn.example.test',
    ]);

    $storage = app(MediaStorage::class);

    $path = $storage->putPublic('properties/1/room.webp', 'the photo of the room');

    expect($storage->publicUrl($path))->toBe('https://cdn.example.test/properties/1/room.webp');

    Storage::disk('public')->assertExists($path);
    Storage::disk('local')->assertMissing($path);
});
