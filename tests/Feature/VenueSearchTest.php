<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_natural_search_finds_venue_by_name_street_and_suburb(): void
    {
        $conte = Venue::factory()->create(['name' => 'Bar Conte Sydney', 'address' => 'Shops R02 And Mr02, 151 Clarence St', 'suburb' => 'Sydney', 'postcode' => '2000']);
        Venue::factory()->create(['name' => 'Bar Clarence', 'address' => 'LG 275 Clarence St', 'suburb' => 'Sydney', 'postcode' => '2000']);
        Venue::factory()->create(['name' => 'Conte', 'address' => '340 Riley St', 'suburb' => 'Surry Hills', 'postcode' => '2010']);
        Venue::factory()->create(['name' => 'The Lord Nelson', 'address' => '19 Kent St', 'suburb' => 'Millers Point', 'postcode' => '2000']);

        $names = fn (string $q) => Venue::query()->active()->search($q)->pluck('name')->all();

        $this->assertSame('Bar Conte Sydney', $names('Bar Conte in Clarence Street in Sydney City')[0]);
        $this->assertSame(['Bar Conte Sydney'], $names('bar conte'));
        $this->assertEqualsCanonicalizing(['Bar Conte Sydney', 'Bar Clarence'], $names('Clarence Street Sydney'));
        $this->assertEqualsCanonicalizing(['Bar Conte Sydney', 'Bar Clarence', 'The Lord Nelson'], $names('2000'));
        $this->assertSame([], $names('in the city'));

        $this->withoutVite()->get('/search?q='.urlencode('Bar Conte in Clarence Street in Sydney City'))
            ->assertOk()->assertSeeInOrder(['Bar Conte Sydney', 'Bar Clarence']);
        $this->get('/venues/search?q=bar+conte')->assertRedirect(route('venues.show', $conte));
    }

    public function test_every_result_is_reachable_through_pages(): void
    {
        Venue::factory(45)->sequence(fn ($s) => ['name' => sprintf('Fitzroy Bar %02d', $s->index), 'suburb' => 'Fitzroy'])->create();

        $page1 = $this->withoutVite()->get('/search?q=fitzroy')->assertOk();
        $this->assertSame(45, $page1->viewData('venues')->total());
        $this->assertCount(20, $page1->viewData('venues')->items());
        $page1->assertSee('45 matches')->assertSee('page 1 of 3');

        $page3 = $this->withoutVite()->get('/search?q=fitzroy&page=3')->assertOk();
        $this->assertCount(5, $page3->viewData('venues')->items());

        $seen = collect([1, 2, 3])->flatMap(fn ($p) => $this->withoutVite()->get("/search?q=fitzroy&page={$p}")->viewData('venues')->pluck('id'));
        $this->assertSame(45, $seen->unique()->count(), 'no venue missing or repeated across pages');
    }

    public function test_nearby_list_pages_with_show_more(): void
    {
        config(['beercheck.geo.max_results' => 2]);
        foreach (range(1, 5) as $i) {
            Venue::factory()->at(-37.8136 + $i * 0.001, 144.9631)->create(['name' => "Pub {$i}"]);
        }

        $first = $this->getJson('/venues/nearby?lat=-37.8136&lng=144.9631&radius=5')->assertOk();
        $this->assertSame(['Pub 1', 'Pub 2'], array_column($first->json('venues'), 'name'));
        $this->assertTrue($first->json('has_more'));

        $second = $this->getJson('/venues/nearby?lat=-37.8136&lng=144.9631&radius=5&offset='.$first->json('next_offset'))->assertOk();
        $this->assertSame(['Pub 3', 'Pub 4'], array_column($second->json('venues'), 'name'));

        $last = $this->getJson('/venues/nearby?lat=-37.8136&lng=144.9631&radius=5&offset=4')->assertOk();
        $this->assertSame(['Pub 5'], array_column($last->json('venues'), 'name'));
        $this->assertFalse($last->json('has_more'));
    }
}
