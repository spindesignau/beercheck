<?php

namespace Tests\Feature;

use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -37.8136;

    private const LNG = 144.9631;

    public function test_nearby_returns_venues_in_radius_sorted_by_distance_with_cheapest_price(): void
    {
        $near = Venue::factory()->at(-37.8140, 144.9635)->create(['name' => 'Near']);      // ~50 m
        $mid = Venue::factory()->at(-37.8300, 144.9631)->create(['name' => 'Mid']);        // ~1.8 km
        Venue::factory()->at(-37.9500, 144.9631)->create(['name' => 'Far']);              // ~15 km
        Venue::factory()->at(-37.8137, 144.9632)->create(['name' => 'Closed', 'is_active' => false]);

        $beer = Beer::factory()->create();
        BeerPrice::factory()->create(['venue_id' => $near->id, 'beer_id' => $beer->id, 'price' => 11]);
        BeerPrice::factory()->create(['venue_id' => $near->id, 'beer_id' => $beer->id, 'price' => 8, 'size' => 'pot']);

        $res = $this->getJson('/venues/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5')->assertOk();

        $this->assertSame(['Near', 'Mid'], array_column($res->json('venues'), 'name'));
        $this->assertSame(8.0, (float) $res->json('venues.0.cheapest.price'));
        $this->assertNull($res->json('venues.1.cheapest'));
        $this->assertLessThan(100, $res->json('venues.0.distance_m'));
    }

    public function test_radius_is_clamped_and_validated(): void
    {
        $this->getJson('/venues/nearby?radius=500')->assertStatus(422);
        $this->getJson('/venues/nearby')->assertOk()->assertJsonPath('radius_km', 5);
    }

    public function test_current_price_uses_latest_post_and_prefers_venue_listed(): void
    {
        $venue = Venue::factory()->at(self::LAT, self::LNG)->create();
        $beer = Beer::factory()->create();

        $old = BeerPrice::factory()->create(['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => 5.00]);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        BeerPrice::factory()->create(['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => 9.00]);

        $res = $this->getJson('/beers/cheapest?lat='.self::LAT.'&lng='.self::LNG)->assertOk();
        $this->assertEquals([9.0], array_column($res->json('results'), 'price'), 'stale cheaper post must not win');

        BeerPrice::factory()->official()->create(['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => 10.00])
            ->forceFill(['created_at' => now()->subDays(20)])->save();

        $res = $this->getJson('/beers/cheapest?lat='.self::LAT.'&lng='.self::LNG)->assertOk();
        $this->assertEquals(10.0, $res->json('results.0.price'));
        $this->assertTrue($res->json('results.0.is_venue_listed'));
    }

    public function test_filter_engine_returns_top_3_cheapest_matching(): void
    {
        $beer = Beer::factory()->create(['brand' => 'Carlton', 'name' => 'Draught']);
        $other = Beer::factory()->create(['brand' => 'Coopers', 'name' => 'Pale Ale']);

        foreach ([7.5, 6.0, 9.0, 8.0, 4.0, 25.0] as $i => $price) {
            BeerPrice::factory()->create([
                'venue_id' => Venue::factory()->at(self::LAT + $i * 0.001, self::LNG)->create()->id,
                'beer_id' => $beer->id, 'price' => $price,
            ]);
        }
        BeerPrice::factory()->create(['venue_id' => Venue::factory()->at(self::LAT, self::LNG)->create()->id, 'beer_id' => $other->id, 'price' => 5.5]);
        BeerPrice::factory()->create(['venue_id' => Venue::factory()->at(self::LAT, self::LNG)->create()->id, 'beer_id' => $beer->id, 'price' => 5.0, 'size' => 'pint']);

        $res = $this->getJson('/beers/cheapest?'.http_build_query([
            'lat' => self::LAT, 'lng' => self::LNG, 'radius' => 10,
            'brand' => 'Carlton', 'size' => 'schooner', 'min_price' => 5, 'max_price' => 20,
        ]))->assertOk();

        $this->assertEquals([6.0, 7.5, 8.0], array_column($res->json('results'), 'price'));
    }

    public function test_beer_search_lists_venues_by_cheapest_price(): void
    {
        $beer = Beer::factory()->create(['brand' => 'Great Northern', 'name' => 'Original']);
        BeerPrice::factory()->create(['venue_id' => Venue::factory()->at(self::LAT, self::LNG)->create()->id, 'beer_id' => $beer->id, 'price' => 10]);
        BeerPrice::factory()->create(['venue_id' => Venue::factory()->at(self::LAT, self::LNG)->create()->id, 'beer_id' => $beer->id, 'price' => 7]);

        $res = $this->getJson('/beers/cheapest?q=northern&lat='.self::LAT.'&lng='.self::LNG)->assertOk();
        $this->assertSame('search', $res->json('mode'));
        $this->assertEquals([7.0, 10.0], array_column($res->json('results'), 'price'));

        $this->withoutVite()->get('/beers/cheapest?q=northern&lat='.self::LAT.'&lng='.self::LNG)->assertOk()->assertSee('Great Northern Original');
    }

    public function test_venue_search_redirects_on_single_match_and_venue_page_renders(): void
    {
        $venue = Venue::factory()->create(['name' => 'The Unique Arms', 'postcode' => '3065']);
        $beer = Beer::factory()->create();
        BeerPrice::factory()->official()->create(['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => 8]);
        BeerPrice::factory()->create(['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => 7, 'size' => 'pot', 'rating' => 4, 'review_text' => 'Lovely beer garden']);

        $this->get('/venues/search?q=unique+arms')->assertRedirect(route('venues.show', $venue));

        $this->withoutVite()->get(route('venues.show', $venue))->assertOk()
            ->assertSee('The Unique Arms')->assertSee('Venue price')->assertSee('Punter price')->assertSee('Lovely beer garden')->assertSee('Post a Price here');
    }

    public function test_home_page_renders(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertSee('Cheapest beer')->assertSee('Nearby pubs');
    }

    public function test_unified_search_returns_pubs_and_beers(): void
    {
        Venue::factory()->create(['name' => 'The Carlton Club Hotel', 'suburb' => 'Carlton']);
        Beer::factory()->create(['brand' => 'Carlton', 'name' => 'Draught']);
        Beer::factory()->pending()->create(['brand' => 'Carlton', 'name' => 'Secret Pending']);

        $this->withoutVite()->get('/search?q=carlton')->assertOk()
            ->assertSee('The Carlton Club Hotel')
            ->assertSee('Carlton Draught')
            ->assertDontSee('Secret Pending');

        $this->withoutVite()->get('/search')->assertOk()->assertSee('What are you after?');
        // Old venue search URL still works: multiple matches go to the unified search.
        Venue::factory()->create(['name' => 'Carlton Arms', 'suburb' => 'Carlton']);
        $this->get('/venues/search?q=carlton')->assertRedirect(route('search', ['q' => 'carlton']));
    }

    public function test_top3_page_asks_for_location_then_renders_filters(): void
    {
        $this->withoutVite()->get('/beers/cheapest')->assertOk()->assertSee('Finding beers near you');
        $this->withoutVite()->get('/beers/cheapest?lat='.self::LAT.'&lng='.self::LNG)->assertOk()->assertSee('Top 3 cheapest')->assertSee('Show the top 3');
    }
}
