<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Punter suggestion of a missing beer (goes to the admin moderation queue). */
class StoreBeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand' => preg_replace('/\s+/', ' ', trim((string) $this->input('brand'))),
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'min:2', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:150',
                Rule::unique('beers', 'name')->where('brand', $this->input('brand'))],
            'type_id' => ['required', 'integer', 'exists:beer_types,id'],
            'abv' => ['nullable', 'numeric', 'between:0,20'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'That beer is already listed (or awaiting approval).'];
    }
}
