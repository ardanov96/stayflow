<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
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
            'file' => ['required', 'file', 'image', 'max:10240'], // 10MB max, valid image
            'mediable_type' => ['required', 'string', 'in:property,room_type'],
            'mediable_id' => ['required', 'integer'],
            'caption' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
