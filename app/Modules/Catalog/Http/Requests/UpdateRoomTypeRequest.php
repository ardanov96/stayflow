<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'base_occupancy' => ['sometimes', 'integer', 'min:1'],
            'max_adults' => ['sometimes', 'integer', 'min:1'],
            'max_children' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(RoomTypeStatus::class)],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
        ];
    }
}
