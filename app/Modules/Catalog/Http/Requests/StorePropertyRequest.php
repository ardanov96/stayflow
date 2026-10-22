<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3'],
            'check_in_time' => ['nullable', 'string', 'regex:/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/'],
            'check_out_time' => ['nullable', 'string', 'regex:/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
        ];
    }
}
