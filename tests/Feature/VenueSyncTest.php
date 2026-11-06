<?php

namespace Tests\Feature;

use App\Enums\VenueSource;
use App\Models\GeocodeCache;
use App\Models\Venue;
use App\Services\Sync\SpreadsheetFeed;
use App\Services\Sync\VenueSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenueSyncTest extends TestCase
{
    use RefreshDatabase;

    private function feed(): SpreadsheetFeed
    {
        return new SpreadsheetFeed(VenueSource::VicLiquor, base_path('tests/fixtures/vic_licences.csv'), 'csv');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['beercheck.geocoding.throttle_ms' => 0]);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '-37.8000', 'lon' => '144.9670']]),
        ]);
    }

    public function test_imports_only_pubs_and_bars_and_geocodes_missing_coordinates(): void
    {
        $run = app(VenueSyncService::class)->run(VenueSource::VicLiquor, $this->feed());

        $this->assertSame('completed', $run->status, (string) $run->error_log);
        $this->assertSame(6, $run->fetched_count);
        $this->assertSame(3, $run->filtered_count);  // packaged, restaurant, club
        $this->assertSame(3, $run->inserted_count);
        $this->assertSame(1, $run->geocoded_count);

        $this->assertEqualsCanonicalizing(
            ['32001', '32002', '32006'],
            Venue::pluck('external_id')->all(),
        );
        $green = Venue::firstWhere('external_id', '32001');
        $this->assertSame('The Green Man Hotel', $green->name);
        $this->assertSame('VIC', $green->state);
        $this->assertSame(-37.802, $green->latitude);
        $this->assertSame(-37.8, Venue::firstWhere('external_id', '32006')->latitude);
    }

    public function test_second_run_updates_uses_geocode_cache_and_deactivates_missing(): void
    {
        $sync = app(VenueSyncService::class);
        $sync->run(VenueSource::VicLiquor, $this->feed());

        $gone = Venue::factory()->create(['source' => VenueSource::VicLiquor, 'external_id' => '99999', 'last_synced_at' => now()->subWeek()]);
        $manual = Venue::factory()->create(); // other sources are never touched
        $this->travel(1)->minutes();

        $run = $sync->run(VenueSource::VicLiquor, $this->feed());

        $this->assertSame(0, $run->inserted_count);
        $this->assertSame(3, $run->updated_count);
        $this->assertSame(1, $run->deactivated_count);
        $this->assertSame(0, $run->geocoded_count, 'second run must hit the geocode cache');
        $this->assertSame(1, GeocodeCache::count());
        Http::assertSentCount(1);

        $this->assertFalse($gone->fresh()->is_active);
        $this->assertSame('sync_missing', $gone->fresh()->deactivation_reason);
        $this->assertTrue($manual->fresh()->is_active);
        $this->assertSame(5, Venue::count(), 'nothing is ever hard-deleted');
    }

    public function test_truncated_feed_does_not_mass_deactivate(): void
    {
        Venue::factory(10)->sequence(fn ($s) => ['external_id' => 'old'.$s->index])
            ->create(['source' => VenueSource::VicLiquor, 'last_synced_at' => now()->subWeek()]);

        $run = app(VenueSyncService::class)->run(VenueSource::VicLiquor, $this->feed());

        $this->assertSame(0, $run->deactivated_count);
        $this->assertStringContainsString('Deactivation skipped', $run->error_log);
        $this->assertSame(13, Venue::where('is_active', true)->count());
    }

    public function test_admin_deactivated_venues_stay_inactive_but_sync_missing_ones_reactivate(): void
    {
        $sync = app(VenueSyncService::class);
        $sync->run(VenueSource::VicLiquor, $this->feed());
        Venue::where('external_id', '32001')->update(['is_active' => false, 'deactivation_reason' => 'admin']);
        Venue::where('external_id', '32002')->update(['is_active' => false, 'deactivation_reason' => 'sync_missing']);

        $sync->run(VenueSource::VicLiquor, $this->feed());

        $this->assertFalse(Venue::firstWhere('external_id', '32001')->is_active);
        $this->assertTrue(Venue::firstWhere('external_id', '32002')->is_active);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('app:sync-venues', [
            '--source' => ['vic_liquor'], '--file' => base_path('tests/fixtures/vic_licences.csv'), '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Venue::count());
    }

    public function test_weekly_schedule_is_registered(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('app:sync-venues')->assertSuccessful();
    }
}
