<?php

namespace Database\Factories;

use App\Enums\VenueSource;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'The '.fake()->unique()->lastName().' Hotel',
            'address' => fake()->streetAddress(),
            'suburb' => 'Melbourne',
            'state' => 'VIC',
            'postcode' => '3000',
            // Within ~3 km of Melbourne CBD.
            'latitude' => -37.8136 + fake()->randomFloat(5, -0.02, 0.02),
            'longitude' => 144.9631 + fake()->randomFloat(5, -0.02, 0.02),
            'source' => VenueSource::Manual,
            'is_active' => true,
        ];
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(['latitude' => $lat, 'longitude' => $lng]);
    }
}
