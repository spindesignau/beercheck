<?php

namespace App\Http\Requests;

use App\Enums\BeerSize;
use App\Enums\BeerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeerPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $prices = config('beercheck.prices');
        $img = config('beercheck.images');

        return [
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')->where('is_active', true)],
            'beer_id' => ['required', 'integer', Rule::exists('beers', 'id')->where('status', BeerStatus::Approved->value)],
            'size' => ['required', Rule::enum(BeerSize::class)],
            'price' => ['required', 'numeric', 'decimal:0,2', "min:{$prices['min']}", "max:{$prices['max']}"],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$img['max_upload_kb']}", 'dimensions:min_width=200,min_height=200'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
