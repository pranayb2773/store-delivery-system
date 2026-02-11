<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\NormalizePostcodeAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CanDeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postcode' => ['required', 'string', Rule::exists('postcodes', 'postcode')],
            'store_id' => ['sometimes', 'integer', Rule::exists('stores', 'id')],
        ];
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
