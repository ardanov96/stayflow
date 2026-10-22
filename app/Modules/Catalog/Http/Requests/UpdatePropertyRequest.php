<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(PropertyStatus::class)],
            'timezone' => ['sometimes', 'string', 'max:64', 'timezone:all'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'check_in_time' => ['sometimes', 'string', 'regex:/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/'],
            'check_out_time' => ['sometimes', 'string', 'regex:/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
        ];
    }
}
