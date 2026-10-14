<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorChallengeRequest extends FormRequest
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
            // The input is auto-dispatched by the action: 6 digits
            // is treated as a TOTP code, 10 chars in the recovery
            // alphabet is treated as a recovery code. We do not
            // validate the format here so the error message stays
            // uniform ("That code is not correct") regardless of
            // which path was tried.
            'code' => ['required', 'string', 'min:6', 'max:10'],
        ];
    }

    public function code(): string
    {
        return (string) $this->input('code', '');
    }
}
