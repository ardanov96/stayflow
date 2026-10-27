<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetRestrictionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer'],
            'rate_plan_id' => ['required', 'integer'],
            'room_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'min_stay' => ['nullable', 'integer', 'min:1'],
            'max_stay' => ['nullable', 'integer', 'min:1'],
            'cta' => ['nullable', 'boolean'],
            'ctd' => ['nullable', 'boolean'],
            'stop_sell' => ['nullable', 'boolean'],
        ];
    }
}
