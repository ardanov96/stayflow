<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The request of the staff to modify a reservation.
 *
 * Everything is optional: the staff only sends what changes.
 */
final class ModifyReservationRequest extends FormRequest
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
            'arrival' => ['sometimes', 'date_format:Y-m-d'],
            'departure' => ['sometimes', 'date_format:Y-m-d', 'after:arrival'],
            'room_type_id' => ['sometimes', 'integer', 'min:1'],
            'rate_plan_id' => ['sometimes', 'integer', 'min:1'],
            'adults' => ['sometimes', 'integer', 'min:1'],
            'children' => ['sometimes', 'integer', 'min:0'],
            'guest_id' => ['sometimes', 'integer', 'min:1'],
            'companion_guest_ids' => ['sometimes', 'array'],
            'companion_guest_ids.*' => ['integer', 'min:1'],
        ];
    }
}
