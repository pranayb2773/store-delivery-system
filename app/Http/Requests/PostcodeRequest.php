<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PostcodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'postcode' => ['required'],
            'latitude' => ['required'],
            'longitude' => ['required'], //
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
