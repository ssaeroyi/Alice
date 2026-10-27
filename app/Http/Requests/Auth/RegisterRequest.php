<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        $banned = array_map('mb_strtolower', config('placement.banned_names'));

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nickname' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\pL\pN _-]+$/u',
                'unique:users,nickname',
                function (string $attribute, mixed $value, Closure $fail) use ($banned): void {
                    if (in_array(mb_strtolower($value), $banned, true)) {
                        $fail('Please choose a different name.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'not_regex:/\s/', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^a-zA-Z0-9]/', 'confirmed'],
            'terms' => ['accepted'],
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
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'nickname.required' => 'Please choose your preferred username.',
            'nickname.max' => 'Your username must be 20 characters or fewer.',
            'nickname.regex' => 'Your username can only use letters, numbers, spaces, hyphens, and underscores.',
            'nickname.unique' => 'That name is already taken. Please choose another.',
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.not_regex' => 'Password must not contain spaces.',
            'password.regex' => 'Password must contain at least 1 lowercase letter, 1 uppercase letter, 1 number, and 1 special character.',
            'password.confirmed' => 'The passwords do not match.',
            'terms.accepted' => 'Please accept the Terms & Conditions to continue.',
        ];
    }
}
