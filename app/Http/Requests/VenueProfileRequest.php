<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Fields a publican may edit on their venue profile. */
class VenueProfileRequest extends FormRequest
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('venue'));
    }

    public function rules(): array
    {
        $rules = [
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s\+\(\)\-]+$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'opening_hours' => ['nullable', 'array'],
        ];
        foreach (self::DAYS as $day) {
            $rules["opening_hours.{$day}"] = ['nullable', 'string', 'max:40'];
        }

        return $rules;
    }
}
