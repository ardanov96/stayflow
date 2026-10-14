<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:1', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function email(): string
    {
        return (string) $this->input('email', '');
    }

    public function password(): string
    {
        return (string) $this->input('password', '');
    }

    public function shouldRemember(): bool
    {
        return (bool) $this->boolean('remember');
    }
}