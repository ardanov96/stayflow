<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'base_occupancy' => ['required', 'integer', 'min:1'],
            'max_adults' => ['required', 'integer', 'gte:base_occupancy'],
            'max_children' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(RoomTypeStatus::class)],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
        ];
    }
}
