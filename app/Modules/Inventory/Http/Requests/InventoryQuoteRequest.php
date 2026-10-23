<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InventoryQuoteRequest extends FormRequest
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
            'room_type_ids' => ['nullable', 'array'],
            'room_type_ids.*' => ['integer'],
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'check_out_date' => ['required', 'date_format:Y-m-d', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rate_plan_id' => ['nullable', 'integer'],
        ];
    }
}
