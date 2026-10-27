<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateTeacherRequest extends FormRequest
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
            'nickname' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\pL\pN _-]+$/u',
                'unique:users,nickname',
                function (string $attribute, mixed $value, Closure $fail) use ($banned): void {
                    if (in_array(mb_strtolower($value), $banned, true)) {
                        $fail('Please choose a different username.');
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::where('email', $value)->exists()) {
                        $fail('An account with this email already exists. If this person is a student, change their role from their account page instead.');
                    }
                },
            ],
            'password' => ['required', 'string', Password::min(8), 'max:255', 'not_regex:/\s/', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^a-zA-Z0-9]/'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
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
            'nickname.required' => 'Please enter a username.',
            'nickname.max' => 'The username must be 20 characters or fewer.',
            'nickname.regex' => 'The username can only use letters, numbers, spaces, hyphens, and underscores.',
            'nickname.unique' => 'That username is already taken.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter a temporary password.',
            'password.min' => 'The temporary password must be at least 8 characters.',
            'password.not_regex' => 'Password must not contain spaces.',
            'password.regex' => 'Password must contain at least 1 lowercase letter, 1 uppercase letter, 1 number, and 1 special character.',
            'first_name.required' => 'Please enter a first name.',
            'last_name.required' => 'Please enter a last name.',
        ];
    }
}
