<?php

namespace App\Http\Requests;

use App\Enums\BeerSize;
use App\Enums\BeerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficialPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('venue'));
    }

    public function rules(): array
    {
        $prices = config('beercheck.prices');

        return [
            'beer_id' => ['required', 'integer', Rule::exists('beers', 'id')->where('status', BeerStatus::Approved->value)],
            'size' => ['required', Rule::enum(BeerSize::class)],
            'price' => ['required', 'numeric', 'decimal:0,2', "min:{$prices['min']}", "max:{$prices['max']}"],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('beercheck.images.max_upload_kb')],
        ];
    }
}
