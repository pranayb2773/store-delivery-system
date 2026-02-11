<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\NormalizePostcodeAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class NearByStoresRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'postcode' => ['required', 'string', Rule::exists('postcodes', 'postcode')],
            'radius' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'open_now' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('postcode')) {
            $this->merge([
                'postcode' => app(NormalizePostcodeAction::class)->handle($this->input('postcode')),
            ]);
        }
    }
}
