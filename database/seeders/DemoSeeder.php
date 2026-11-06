<?php

namespace Database\Seeders;

use App\Enums\BeerSize;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/** Local-only demo data around Melbourne CBD. Admin login: admin@beercheck.test / password */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin', 'email' => 'admin@beercheck.test']);
        $punters = User::factory(5)->create();
        $venues = Venue::factory(25)->create();
        $beers = Beer::approved()->get();

        foreach ($venues as $venue) {
            foreach ($beers->random(4) as $beer) {
                $p = new BeerPrice([
                    'beer_id' => $beer->id, 'venue_id' => $venue->id,
                    'size' => fake()->randomElement([BeerSize::Pot, BeerSize::Schooner, BeerSize::Pint])->value,
                    'price' => fake()->randomFloat(1, 5.5, 15),
                    'rating' => fake()->optional()->numberBetween(2, 5),
                ]);
                $p->user_id = $punters->random()->id;
                $p->is_venue_listed = false;
                $p->save();
            }
        }
    }
}
