<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvatarRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $files = collect(config('avatars', []))->pluck('file')->all();

        return [
            'avatar' => [
                'required',
                'string',
                Rule::in($files),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Please choose an avatar.',
            'avatar.in' => 'That is not an available avatar.',
        ];
    }
}
