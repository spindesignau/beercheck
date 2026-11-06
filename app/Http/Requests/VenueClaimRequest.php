<?php

namespace App\Http\Requests;

use App\Enums\VenueManagerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['required', 'integer', 'exists:venues,id',
                Rule::unique('venue_managers', 'venue_id')
                    ->where('user_id', $this->user()->id)
                    ->whereNot('status', VenueManagerStatus::Rejected->value)],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email:rfc', 'max:255'],
            'abn' => ['nullable', 'digits:11'],
        ];
    }

    public function messages(): array
    {
        return ['venue_id.unique' => 'You already have a claim for this venue.'];
    }
}
