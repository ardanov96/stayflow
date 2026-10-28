<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The request of the staff to create a reservation.
 */
final class CreateReservationRequest extends FormRequest
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
            'property_id' => ['required', 'integer', 'min:1'],
            'room_type_id' => ['required', 'integer', 'min:1'],
            'rate_plan_id' => ['required', 'integer', 'min:1'],
            'arrival' => ['required', 'date_format:Y-m-d'],
            'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'guest_id' => ['required', 'integer', 'min:1'],
            'companion_guest_ids' => ['sometimes', 'array'],
            'companion_guest_ids.*' => ['integer', 'min:1'],
            // The key of the request: the same key creates one reservation,
            // never two.
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
