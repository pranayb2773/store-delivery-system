<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'address_line1' => ['required'],
            'city' => ['required'],
            'postcode' => ['required'],
            'latitude' => ['required', 'decimal:2'],
            'longitude' => ['required', 'decimal:2'],
            'delivery_radius_km' => ['required', 'decimal:2'],
            'is_active' => ['boolean'],
            'opening_hours' => ['required'], //
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
