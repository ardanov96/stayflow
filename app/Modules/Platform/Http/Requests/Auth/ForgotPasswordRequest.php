<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ];
    }

    public function email(): string
    {
        return (string) $this->input('email', '');
    }
}
