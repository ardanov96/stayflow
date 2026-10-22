<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRuleRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(TaxKind::class)],
            'rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_inclusive' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', Rule::enum(TaxAppliesTo::class)],
            'order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
