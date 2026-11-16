<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_suburb_and_postcode_lookup_use_the_median_venue_position(): void
    {
        foreach ([[-37.799, 144.978], [-37.800, 144.979], [-37.798, 144.977]] as [$lat, $lng]) {
            Venue::factory()->at($lat, $lng)->create(['suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065']);
        }
        // A mis-pinned register entry ~400 km away must not drag the suburb centre.
        Venue::factory()->at(-34.18, 142.16)->create(['suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065']);
        Venue::factory()->at(-37.785, 144.981)->create(['suburb' => 'Fitzroy North', 'state' => 'VIC', 'postcode' => '3068']);
        Venue::factory()->create(['suburb' => 'Fitzroy', 'state' => 'VIC', 'postcode' => '3065', 'is_active' => false]);

        $res = $this->getJson('/places/lookup?q=fitz')->assertOk();
        $this->assertSame(['Fitzroy VIC 3065', 'Fitzroy North VIC 3068'], array_column($res->json(), 'label'));
        $this->assertSame(4, $res->json('0.venues'));
        $this->assertEqualsWithDelta(-37.7995, $res->json('0.lat'), 0.002);
        $this->assertEqualsWithDelta(144.9785, $res->json('0.lng'), 0.002);

        $this->getJson('/places/lookup?q=3068')->assertOk()->assertJsonPath('0.label', 'Fitzroy North VIC 3068');
        $this->getJson('/places/lookup?q=x')->assertStatus(422);
    }
}
