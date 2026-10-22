<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Ports\ImageOptimizer;
use App\Modules\Catalog\Infrastructure\Models\CatalogMedia;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Action to validate, optimize to WebP, and attach media to a Property or RoomType.
 */
final readonly class UploadCatalogMediaAction
{
    public function __construct(
        private ImageOptimizer $optimizer,
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     caption?: array<string, string>|null,
     *     sort_order?: int,
     *     is_primary?: bool
     * } $metadata
     */
    public function execute(
        Property|RoomType $mediable,
        UploadedFile $file,
        array $metadata = [],
        ?int $actorId = null,
    ): CatalogMedia {
        $propertyId = $mediable instanceof Property ? $mediable->id : $mediable->property_id;
        $organizationId = $mediable->organization_id;

        if ($propertyId <= 0 || $organizationId <= 0) {
            throw new InvalidArgumentException('Mediable model must have organization_id and property_id.');
        }

        $optimized = $this->optimizer->optimize(
            file: $file,
            disk: 'public',
            directory: "catalog/{$propertyId}",
        );

        $media = CatalogMedia::create([
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'mediable_type' => $mediable->getMorphClass(),
            'mediable_id' => $mediable->getKey(),
            'disk' => $optimized->disk,
            'file_path' => $optimized->filePath,
            'file_name' => $optimized->fileName,
            'mime_type' => $optimized->mimeType,
            'file_size' => $optimized->fileSize,
            'width' => $optimized->width,
            'height' => $optimized->height,
            'caption' => $metadata['caption'] ?? null,
            'sort_order' => $metadata['sort_order'] ?? 0,
            'is_primary' => $metadata['is_primary'] ?? false,
        ]);

        $this->auditor->recordCustom(
            'catalog.media.uploaded',
            new SubjectChange(
                subjectType: CatalogMedia::class,
                subjectId: (int) $media->id,
                oldValues: null,
                newValues: [
                    'file_path' => $media->file_path,
                    'mediable_type' => $media->mediable_type,
                    'mediable_id' => $media->mediable_id,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $propertyId,
                ],
            ),
            new TenantHint((int) $organizationId, (int) $propertyId),
        );

        return $media;
    }
}
