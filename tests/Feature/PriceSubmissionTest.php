<?php

namespace Tests\Feature;

use App\Enums\PointsEventType;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\PointsEvent;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PriceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function post_(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post('/prices', array_merge([
            'venue_id' => Venue::factory()->create()->id,
            'beer_id' => Beer::factory()->create()->id,
            'size' => 'schooner',
            'price' => '9.50',
        ], $overrides));
    }

    public function test_price_photo_and_review_earn_20_points_with_ledger(): void
    {
        $user = User::factory()->create();

        $this->post_($user, [
            'photo' => UploadedFile::fake()->image('pint.jpg', 4000, 3000),
            'rating' => 4,
            'review_text' => 'Cold, crisp and poured properly. Will be back.',
        ])->assertRedirect();

        $this->assertSame(20, $user->fresh()->points);
        $this->assertEqualsCanonicalizing(
            ['price_posted', 'photo_uploaded', 'review_written'],
            PointsEvent::pluck('event_type')->map->value->all(),
        );
        $this->assertSame(20, (int) PointsEvent::where('user_id', $user->id)->sum('points_awarded'));
    }

    public function test_photo_is_resized_to_max_1600_wide_jpeg(): void
    {
        $user = User::factory()->create();
        $this->post_($user, ['photo' => UploadedFile::fake()->image('big.png', 4000, 3000)]);

        $price = BeerPrice::firstOrFail();
        $this->assertStringEndsWith('.jpg', $price->photo_path);
        Storage::disk('public')->assertExists($price->photo_path);

        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($price->photo_path));
        $this->assertSame(1600, $w);
        $this->assertSame(1200, $h);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    public function test_price_only_earns_5_and_short_review_earns_nothing_extra(): void
    {
        $user = User::factory()->create();
        $this->post_($user, ['review_text' => 'Nice', 'rating' => 5]);

        $this->assertSame(5, $user->fresh()->points);
    }

    public function test_repeat_post_within_cooldown_earns_no_points(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        $beer = Beer::factory()->create();

        $this->post_($user, ['venue_id' => $venue->id, 'beer_id' => $beer->id]);
        $this->post_($user, ['venue_id' => $venue->id, 'beer_id' => $beer->id, 'price' => '9.00']);

        $this->assertSame(2, BeerPrice::count());
        $this->assertSame(5, $user->fresh()->points);
    }

    public function test_client_cannot_set_points_or_venue_listed_flag(): void
    {
        $user = User::factory()->create();
        $this->post_($user, ['is_venue_listed' => 1, 'points' => 999, 'user_id' => 12345]);

        $price = BeerPrice::firstOrFail();
        $this->assertFalse($price->is_venue_listed);
        $this->assertSame($user->id, $price->user_id);
        $this->assertSame(5, $user->fresh()->points);
    }

    public function test_pending_beers_and_inactive_venues_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post_($user, ['beer_id' => Beer::factory()->pending()->create()->id])->assertSessionHasErrors('beer_id');
        $this->post_($user, ['venue_id' => Venue::factory()->create(['is_active' => false])->id])->assertSessionHasErrors('venue_id');
    }

    public function test_guests_must_log_in(): void
    {
        $this->post('/prices', [])->assertRedirect(route('login'));
    }

    public function test_moderator_delete_reverses_points(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->post_($user, ['review_text' => str_repeat('Great pub, great beer. ', 2)]);
        $price = BeerPrice::firstOrFail();
        $this->assertSame(15, $user->fresh()->points);

        $this->actingAs($admin)->delete(route('admin.prices.destroy', $price))->assertRedirect();

        $this->assertSoftDeleted($price);
        $this->assertSame(0, $user->fresh()->points);
        $this->assertDatabaseHas('points_events', [
            'user_id' => $user->id, 'event_type' => PointsEventType::ModerationReversal->value, 'points_awarded' => -15,
        ]);
    }
}
