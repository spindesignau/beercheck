<?php

namespace App\Http\Requests;

use App\Rules\MinimumAge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'username' => trim((string) $this->input('username')),
            'mobile' => filled($this->input('mobile')) ? preg_replace('/[^\d+]/', '', (string) $this->input('mobile')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_\.]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'regex:/^(\+?61|0)4\d{8}$/'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', new MinimumAge(config('beercheck.registration.minimum_age'))],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter an Australian mobile number, e.g. 0412 345 678.',
            'username.regex' => 'Usernames may contain letters, numbers, dots and underscores.',
        ];
    }
}
