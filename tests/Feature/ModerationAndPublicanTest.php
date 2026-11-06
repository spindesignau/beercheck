<?php

namespace Tests\Feature;

use App\Enums\BeerStatus;
use App\Enums\UserRole;
use App\Enums\VenueManagerStatus;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\BeerType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueManager;
use App\Notifications\VerifyBusinessEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ModerationAndPublicanTest extends TestCase
{
    use RefreshDatabase;

    public function test_beer_suggestion_approval_awards_15_points_once(): void
    {
        $punter = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($punter)->post('/beers', ['brand' => 'Hop Nation', 'name' => 'The Buzz', 'type_id' => BeerType::first()->id])->assertRedirect();
        $beer = Beer::firstWhere('name', 'The Buzz');
        $this->assertSame(BeerStatus::Pending, $beer->status);

        $this->actingAs($admin)->post(route('admin.beers.approve', $beer))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.beers.approve', $beer))->assertRedirect(); // idempotent

        $this->assertSame(BeerStatus::Approved, $beer->fresh()->status);
        $this->assertSame(15, $punter->fresh()->points);
    }

    public function test_non_admins_cannot_reach_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->publican()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->withoutVite()->get('/admin')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        BeerPrice::factory()->create();
        $this->actingAs($admin)->withoutVite();

        foreach (['/admin/beers', '/admin/prices', '/admin/venues', '/admin/venues/create', '/admin/claims', '/admin/sync', '/admin/points'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_full_publican_claim_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user)->post('/publican/claim', [
            'venue_id' => $venue->id, 'business_name' => 'Pub Co Pty Ltd', 'business_email' => 'owner@pubco.com.au',
        ])->assertRedirect();

        $claim = VenueManager::firstOrFail();
        Notification::assertSentOnDemand(VerifyBusinessEmail::class);
        $this->assertFalse($user->fresh()->managesVenue($venue));

        $this->get(URL::temporarySignedRoute('publican.claim.verify', now()->addDay(), ['claim' => $claim->id]))->assertRedirect();
        $this->assertSame(VenueManagerStatus::EmailVerified, $claim->fresh()->status);

        $this->actingAs($admin)->post(route('admin.claims.approve', $claim))->assertRedirect();
        $user->refresh();
        $this->assertSame(UserRole::Publican, $user->role);
        $this->assertTrue($user->managesVenue($venue));
    }

    public function test_publican_can_post_official_price_and_reply_but_not_for_other_venues(): void
    {
        [$publican, $venue] = $this->approvedPublican();
        $otherVenue = Venue::factory()->create();
        $beer = Beer::factory()->create();

        $this->actingAs($publican)->post(route('publican.prices.store', $venue), ['beer_id' => $beer->id, 'size' => 'pint', 'price' => 12])->assertRedirect();
        $official = BeerPrice::where('venue_id', $venue->id)->firstOrFail();
        $this->assertTrue($official->is_venue_listed);
        $this->assertSame(0, $publican->fresh()->points, 'official prices earn no points');

        $this->actingAs($publican)->post(route('publican.prices.store', $otherVenue), ['beer_id' => $beer->id, 'size' => 'pint', 'price' => 1])->assertForbidden();

        $review = BeerPrice::factory()->create(['venue_id' => $venue->id, 'rating' => 2, 'review_text' => 'Flat beer']);
        $this->actingAs($publican)->put(route('publican.replies.upsert', $review), ['reply_text' => 'Sorry! Lines cleaned since.'])->assertRedirect();
        $this->assertDatabaseHas('review_replies', ['beer_price_id' => $review->id, 'user_id' => $publican->id]);

        $foreign = BeerPrice::factory()->create(['venue_id' => $otherVenue->id, 'review_text' => 'Meh']);
        $this->actingAs($publican)->put(route('publican.replies.upsert', $foreign), ['reply_text' => 'x y'])->assertForbidden();

        $this->actingAs($publican)->put(route('publican.venues.update', $venue), [
            'phone' => '03 9000 0000', 'website' => 'https://example.com', 'opening_hours' => ['mon' => '11am-late'],
        ])->assertRedirect();
        $this->assertSame(['mon' => '11am-late'], $venue->fresh()->opening_hours);
    }

    public function test_admin_deactivation_is_sticky(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($admin)->post(route('admin.venues.toggle', $venue));
        $this->assertFalse($venue->fresh()->is_active);
        $this->assertSame('admin', $venue->fresh()->deactivation_reason);
    }

    /** @return array{0: User, 1: Venue} */
    private function approvedPublican(): array
    {
        $user = User::factory()->publican()->create();
        $venue = Venue::factory()->create();
        $claim = new VenueManager(['venue_id' => $venue->id, 'business_name' => 'X', 'business_email' => 'x@x.com']);
        $claim->user_id = $user->id;
        $claim->status = VenueManagerStatus::Approved;
        $claim->save();

        return [$user, $venue];
    }
}
