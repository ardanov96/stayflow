<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }

    public function email(): string
    {
        return (string) $this->input('email', '');
    }

    public function token(): string
    {
        return (string) $this->input('token', '');
    }

    public function newPassword(): string
    {
        return (string) $this->input('password', '');
    }
}