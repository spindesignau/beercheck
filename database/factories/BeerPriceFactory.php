<?php

namespace Database\Factories;

use App\Enums\BeerSize;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BeerPrice> */
class BeerPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'beer_id' => Beer::factory(),
            'venue_id' => Venue::factory(),
            'price' => fake()->randomFloat(2, 6, 16),
            'size' => BeerSize::Schooner,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (BeerPrice $p) {
            $p->user_id ??= User::factory()->create()->id;
            $p->is_venue_listed ??= false;
        });
    }

    public function official(): static
    {
        return $this->afterMaking(fn (BeerPrice $p) => $p->is_venue_listed = true);
    }
}
