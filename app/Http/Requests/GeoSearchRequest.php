<?php

namespace App\Http\Requests;

use App\Data\PriceFilters;
use App\Enums\BeerSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared validation for nearby / cheapest / filter endpoints. */
class GeoSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $geo = config('beercheck.geo');

        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', "between:{$geo['min_radius_km']},{$geo['max_radius_km']}"],
            'q' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'integer', 'exists:beer_types,id'],
            'size' => ['nullable', Rule::enum(BeerSize::class)],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:1000', 'gte:min_price'],
        ];
    }

    public function filters(array $overrides = []): PriceFilters
    {
        return PriceFilters::fromArray(array_merge($this->validated(), $overrides));
    }
}
