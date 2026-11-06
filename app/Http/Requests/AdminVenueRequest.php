<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'suburb' => ['required', 'string', 'max:100'],
            'state' => ['required', 'in:NSW,VIC,QLD,SA,WA,TAS,ACT,NT'],
            'postcode' => ['required', 'digits:4'],
            'latitude' => ['required', 'numeric', 'between:-44,-9'],
            'longitude' => ['required', 'numeric', 'between:112,154'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'licence_type' => ['nullable', 'string', 'max:120'],
        ];
    }
}
