<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetRatePricesRequest extends FormRequest
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
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'rate_plan_id' => ['required', 'integer', 'exists:rate_plans,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'base_price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'extra_adult_price_minor' => ['nullable', 'integer', 'min:0'],
            'extra_child_price_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
