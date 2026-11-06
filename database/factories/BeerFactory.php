<?php

namespace Database\Factories;

use App\Enums\BeerStatus;
use App\Models\Beer;
use App\Models\BeerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Beer> */
class BeerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand' => fake()->randomElement(['Carlton', 'Victoria', 'Great Northern', 'Coopers', 'Stone & Wood', 'Balter']),
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement(['Lager', 'Pale', 'Draught', 'XPA']),
            'type_id' => fn () => BeerType::query()->value('id'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Beer $b) => $b->status ??= BeerStatus::Approved);
    }

    public function pending(): static
    {
        return $this->afterMaking(fn (Beer $b) => $b->status = BeerStatus::Pending);
    }
}
