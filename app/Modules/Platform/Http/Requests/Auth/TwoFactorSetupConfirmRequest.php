<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorSetupConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    public function code(): string
    {
        return (string) $this->input('code', '');
    }
}
