<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Policy\PolicyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPropertyPolicyRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PolicyType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'structured_terms' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
