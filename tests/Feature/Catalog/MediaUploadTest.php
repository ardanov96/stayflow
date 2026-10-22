<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Application\Actions\UploadCatalogMediaAction;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');

    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->context = app(OrganizationContext::class);
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);
});

function createTestImageFile(string $filename = 'test-image.png'): UploadedFile
{
    // Minimal valid 1x1 PNG bytes so getimagesize() works without needing GD extension
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($filename, $pngBytes);
}

it('uploads and attaches media to property with audit logging', function (): void {
    $action = app(UploadCatalogMediaAction::class);
    $file = createTestImageFile('resort-exterior.png');

    $media = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $action->execute(
            mediable: $this->property,
            file: $file,
            metadata: [
                'caption' => ['en' => 'Resort Main Entrance', 'id' => 'Pintu Masuk Utama'],
                'is_primary' => true,
            ],
            actorId: $this->user->id,
        ),
    );

    expect($media->id)->toBeGreaterThan(0)
        ->and($media->is_primary)->toBeTrue()
        ->and($media->mediable_id)->toBe($this->property->id);

    Storage::disk('public')->assertExists($media->file_path);

    $audit = AuditLog::withoutGlobalScopes()->where('event', 'catalog.media.uploaded')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($media->id);
});

it('allows media upload via HTTP endpoint for a room type', function (): void {
    $roomType = RoomType::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
    ]);

    $file = createTestImageFile('deluxe-king.png');

    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/catalog/media/upload', [
            'mediable_type' => 'room_type',
            'mediable_id' => $roomType->id,
            'file' => $file,
            'is_primary' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.mediable_id', $roomType->id)
        ->assertJsonPath('data.is_primary', true);

    expect($roomType->media()->withoutGlobalScopes()->count())->toBe(1);
});
