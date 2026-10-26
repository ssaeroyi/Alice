<?php

namespace App\Http\Requests\Settings;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $banned = config('placement.banned_names', []);

        return [
            'name' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('users', 'nickname')->ignore($this->user()->id),
                function (string $attribute, mixed $value, Closure $fail) use ($banned): void {
                    if (in_array(mb_strtolower($value), $banned, true)) {
                        $fail('Please choose a different name.');
                    }
                },
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a display name.',
            'name.max' => 'Your name must be 20 characters or fewer.',
            'name.regex' => 'Your name can only use letters, numbers, spaces, hyphens, and underscores.',
            'name.unique' => 'That name is already taken. Please choose another.',
        ];
    }
}
