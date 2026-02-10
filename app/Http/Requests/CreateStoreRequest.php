<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\NormalizePostcodeAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateStoreRequest extends FormRequest
{
    private const array VALID_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    protected function prepareForValidation(): void
    {
        if ($this->has('postcode')) {
            $this->merge([
                'postcode' => app(NormalizePostcodeAction::class)->handle($this->input('postcode')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:254',
                Rule::unique('stores')->where('postcode', $this->input('postcode')),
            ],
            'address_line1' => ['required', 'string', 'max:254'],
            'city' => ['required', 'string', 'max:254'],
            'postcode' => ['required', 'string', Rule::exists('postcodes', 'postcode')],
            'delivery_radius_km' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*' => ['required', 'array:open,close'],
            'opening_hours.*.open' => ['required', 'date_format:H:i'],
            'opening_hours.*.close' => ['required', 'date_format:H:i', 'after:opening_hours.*.open'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_hours.*.open.required' => 'Each day must have an opening time.',
            'opening_hours.*.close.required' => 'Each day must have a closing time.',
            'opening_hours.*.open.date_format' => 'Opening time must be in H:i format (e.g. 09:00).',
            'opening_hours.*.close.date_format' => 'Closing time must be in H:i format (e.g. 17:00).',
            'opening_hours.*.close.after' => 'Closing time must be after opening time.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $openingHours = $this->input('opening_hours');

            if (! is_array($openingHours)) {
                return;
            }

            foreach (array_keys($openingHours) as $day) {
                if (! in_array($day, self::VALID_DAYS, true)) {
                    $validator->errors()->add(
                        "opening_hours.{$day}",
                        "Invalid day '{$day}'. Must be one of: ".implode(', ', self::VALID_DAYS).'.',
                    );
                }
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
