<?php

namespace Tests\Feature;

use App\Models\BeerPrice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_content_seeds_once_is_consistent_and_removes_cleanly(): void
    {
        Storage::fake('public');
        $realVenue = Venue::factory()->create();
        $realUser = User::factory()->create();

        $this->artisan('app:demo-content')->expectsOutputToContain('not enabled')->assertSuccessful();
        $this->assertSame(1, Venue::count(), 'does nothing unless BEERCHECK_DEMO is on');

        config(['beercheck.demo.enabled' => true]);
        $this->artisan('app:demo-content')->assertSuccessful();
        $this->assertGreaterThan(100, Venue::count());
        $this->assertGreaterThan(1000, BeerPrice::count());
        $this->assertTrue(BeerPrice::whereNotNull('photo_path')->exists());
        Storage::disk('public')->assertExists(BeerPrice::whereNotNull('photo_path')->value('photo_path'));

        // Points always match the ledger.
        User::all()->each(fn (User $u) => $this->assertSame((int) $u->pointsEvents()->sum('points_awarded'), $u->points, $u->username));

        // Second run is a no-op.
        $count = BeerPrice::count();
        $this->artisan('app:demo-content')->expectsOutputToContain('already present')->assertSuccessful();
        $this->assertSame($count, BeerPrice::count());

        // Demo venues show up in discovery around Melbourne CBD.
        $this->getJson('/venues/nearby?lat=-37.8136&lng=144.9631&radius=2')->assertOk()->assertJsonPath('venues.0.cheapest.price', fn ($p) => $p > 0);

        $this->artisan('app:demo-content', ['--remove' => true])->assertSuccessful();
        $this->assertSame([$realVenue->id], Venue::pluck('id')->all());
        $this->assertTrue(User::whereKey($realUser->id)->exists());
        $this->assertSame(0, BeerPrice::count());
        Storage::disk('public')->assertMissing('photos/demo');
    }

    public function test_demo_banner_and_noindex(): void
    {
        config(['beercheck.demo.enabled' => true]);
        $this->withoutVite()->get('/')->assertSee('demo venues')->assertSee('noindex', false);

        config(['beercheck.demo.enabled' => false]);
        $this->withoutVite()->get('/')->assertDontSee('demo venues');
    }
}
