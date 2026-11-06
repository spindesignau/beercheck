<?php

namespace App\Data;

use App\Enums\BeerSize;

/**
 * Normalised filter set shared by the Filter Engine ("Top 3 cheapest"),
 * beer search, and the home screen.
 */
final readonly class PriceFilters
{
    /**
     * @param  list<int>|null  $beerIds  restrict to these beers (e.g. from a text search)
     */
    public function __construct(
        public float $lat,
        public float $lng,
        public float $radiusKm,
        public ?string $brand = null,
        public ?int $typeId = null,
        public ?BeerSize $size = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?array $beerIds = null,
    ) {}

    public static function fromArray(array $input): self
    {
        $geo = config('beercheck.geo');

        return new self(
            lat: (float) ($input['lat'] ?? $geo['default_lat']),
            lng: (float) ($input['lng'] ?? $geo['default_lng']),
            radiusKm: (float) min(max((float) ($input['radius'] ?? $geo['default_radius_km']), $geo['min_radius_km']), $geo['max_radius_km']),
            brand: filled($input['brand'] ?? null) ? trim($input['brand']) : null,
            typeId: filled($input['type_id'] ?? null) ? (int) $input['type_id'] : null,
            size: filled($input['size'] ?? null) ? BeerSize::tryFrom($input['size']) : null,
            minPrice: filled($input['min_price'] ?? null) ? (float) $input['min_price'] : null,
            maxPrice: filled($input['max_price'] ?? null) ? (float) $input['max_price'] : null,
            beerIds: $input['beer_ids'] ?? null,
        );
    }

    public function with(array $overrides): self
    {
        return new self(...array_merge(get_object_vars($this), $overrides));
    }
}
