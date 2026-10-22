<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Room\BedConfig;
use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:32'],
            'floor' => ['nullable', 'string', 'max:16'],
            'bed_config' => ['nullable', Rule::enum(BedConfig::class)],
            'status' => ['nullable', Rule::enum(RoomUnitStatus::class)],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
