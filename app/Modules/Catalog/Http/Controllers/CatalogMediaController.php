<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\UploadCatalogMediaAction;
use App\Modules\Catalog\Http\Requests\UploadMediaRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;

class CatalogMediaController extends Controller
{
    public function upload(
        UploadMediaRequest $request,
        UploadCatalogMediaAction $action,
    ): JsonResponse {
        $type = (string) $request->input('mediable_type');
        $id = (int) $request->input('mediable_id');

        $mediable = match ($type) {
            'property' => Property::findOrFail($id),
            'room_type' => RoomType::findOrFail($id),
            default => abort(400, "Unsupported mediable type {$type}"),
        };

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $media = $action->execute(
            mediable: $mediable,
            file: $file,
            metadata: [
                'caption' => $request->input('caption'),
                'sort_order' => (int) $request->input('sort_order', 0),
                'is_primary' => (bool) $request->input('is_primary', false),
            ],
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $media,
        ], 201);
    }
}
