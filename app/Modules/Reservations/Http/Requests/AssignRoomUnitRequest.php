<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The request of the staff to assign a physical room.
 */
final class AssignRoomUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The permission is on the route.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'min:1'],
            'room_unit_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
