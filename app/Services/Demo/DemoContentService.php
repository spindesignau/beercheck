<?php

namespace App\Services\Demo;

use App\Enums\BeerStatus;
use App\Enums\PointsEventType;
use App\Enums\UserRole;
use App\Enums\VenueManagerStatus;
use App\Enums\VenueSource;
use App\Models\Beer;
use App\Models\User;
use App\Models\Venue;
use App\Support\Geo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Fictional demo content for staging: made-up pubs in real Melbourne & Sydney
 * suburbs, demo punters, realistic price ranges, ratings, reviews, publican
 * replies and on-brand illustrated "photos". Everything is tagged so it can be
 * removed in one go (venues: source=manual + external_id "demo-*";
 * users: @demo.beercheck.test emails; photos: photos/demo/).
 */
class DemoContentService
{
    /** [suburb, state, postcode, lat, lng, number of pubs] */
    private const SUBURBS = [
        ['Melbourne', 'VIC', '3000', -37.8136, 144.9631, 14], ['Fitzroy', 'VIC', '3065', -37.7990, 144.9780, 8],
        ['Collingwood', 'VIC', '3066', -37.8020, 144.9880, 6], ['Richmond', 'VIC', '3121', -37.8230, 144.9980, 7],
        ['Brunswick', 'VIC', '3056', -37.7670, 144.9600, 6], ['St Kilda', 'VIC', '3182', -37.8670, 144.9800, 6],
        ['South Yarra', 'VIC', '3141', -37.8390, 144.9920, 4], ['Carlton', 'VIC', '3053', -37.8000, 144.9670, 5],
        ['Footscray', 'VIC', '3011', -37.8000, 144.9000, 4], ['Northcote', 'VIC', '3070', -37.7700, 145.0000, 4],
        ['Sydney', 'NSW', '2000', -33.8688, 151.2093, 12], ['Surry Hills', 'NSW', '2010', -33.8850, 151.2110, 7],
        ['Newtown', 'NSW', '2042', -33.8970, 151.1790, 7], ['Glebe', 'NSW', '2037', -33.8790, 151.1860, 4],
        ['Bondi', 'NSW', '2026', -33.8910, 151.2770, 5], ['Manly', 'NSW', '2095', -33.7970, 151.2880, 4],
        ['Paddington', 'NSW', '2021', -33.8840, 151.2310, 4], ['Balmain', 'NSW', '2041', -33.8590, 151.1790, 4],
        ['Marrickville', 'NSW', '2204', -33.9110, 151.1550, 4], ['Coogee', 'NSW', '2034', -33.9210, 151.2570, 3],
    ];

    private const ADJECTIVES = ['Thirsty', 'Golden', 'Foaming', 'Lucky', 'Sleepy', 'Rusty', 'Jolly', 'Wandering', 'Cheeky', 'Velvet',
        'Hoppy', 'Copper', 'Crooked', 'Salty', 'Laughing', 'Dancing', 'Frothy', 'Merry', 'Bold', 'Sunny', 'Tipsy', 'Bubbly'];

    private const NOUNS = ['Wombat', 'Magpie', 'Platypus', 'Kookaburra', 'Possum', 'Pelican', 'Anchor', 'Barrel', 'Lantern', 'Galah',
        'Emu', 'Cockatoo', 'Bandicoot', 'Quokka', 'Echidna', 'Tap', 'Keg', 'Corkscrew', 'Fox', 'Lyrebird', 'Numbat', 'Bilby'];

    private const SUFFIXES = ['Hotel', 'Hotel', 'Arms', 'Tavern', 'Bar', 'Alehouse', 'Social Club', 'Brewhouse'];

    private const STREETS = ['Smith St', 'Brunswick St', 'Chapel St', 'Lygon St', 'King St', 'Oxford St', 'Crown St', 'High St',
        'Victoria St', 'Church St', 'Station St', 'George St', 'Elizabeth St', 'Swan St', 'Johnston St', 'Glebe Point Rd'];

    private const PUNTERS = ['frothy_fran', 'schooner_steve', 'pint_pete', 'hoppy_hannah', 'thirsty_thursday', 'bargain_bev',
        'craft_kev', 'lager_lou', 'stout_sam', 'tinnie_tina', 'jug_julia', 'happy_hour_harry', 'pub_quiz_priya', 'cold_one_cooper'];

    private const REVIEWS = [
        5 => ['Ice cold and poured perfectly. Instant favourite.', 'Best value pint in the suburb, hands down.', 'Lines are clean, beer is crisp, staff are legends.',
            'Beer garden on a sunny arvo with this? Perfect.', 'Happy hour price and still tasted fresh. Winner.'],
        4 => ['Solid pour, good head on it. Will be back.', 'Great beer, bit of a wait at the bar on a Friday.', 'Fair price for the area and nice and cold.',
            'Good selection on tap and the pot was the perfect size.', 'Friendly bartender recommended this one. Good call.'],
        3 => ['Decent but a little pricey for a schooner.', 'Fine beer, nothing special. Venue was packed.', 'Bit warm by the time I got a seat outside.'],
        2 => ['Tasted a bit flat tonight, lines might need a clean.', 'Pricey for what it is. Good spot though.'],
        1 => ['Flat and warm. Expected better.'],
    ];

    private const REPLIES = ['Thanks for the kind words, see you at the bar soon!', 'Cheers for coming in! Happy hour is 4-6pm weekdays.',
        'Thanks for the feedback, we\'ve had the lines cleaned this week.', 'Sorry it wasn\'t up to scratch, come back and the next one is on us.'];

    /** Typical Melbourne/Sydney pub pricing by glass (AUD). */
    private const PRICE_RANGES = [
        'pot' => [6.5, 9.0], 'schooner' => [9.0, 12.5], 'pint' => [11.0, 15.5], 'jug' => [24.0, 34.0],
        'stubby' => [8.0, 11.0], 'can' => [8.0, 12.0], 'longneck' => [11.0, 15.0],
    ];

    private const PHOTO_STYLES = ['Lager' => 'lager', 'Pale Ale' => 'pale-ale', 'Ale' => 'ale', 'IPA' => 'ipa', 'Stout' => 'stout',
        'Porter' => 'stout', 'Wheat' => 'wheat', 'Cider' => 'cider', 'Sour' => 'sour', 'Mid-strength' => 'lager', 'Other' => 'pale-ale'];

    public function exists(): bool
    {
        return Venue::query()->where('external_id', 'like', config('beercheck.demo.external_prefix').'%')->exists();
    }

    /** @return array<string, int> counts */
    public function seed(int $seed = 2026): array
    {
        mt_srand($seed);
        $now = now();

        return DB::transaction(function () use ($now) {
            $photos = $this->publishPhotos();
            $punters = $this->createUsers();
            $publican = $punters['__publican'];
            unset($punters['__publican']);
            $venues = $this->createVenues();
            $beers = Beer::query()->where('status', BeerStatus::Approved)->with('type:id,name')->get();
            abort_if($beers->isEmpty(), 500, 'Run the BeerSeeder first.');

            // Demo publican manages the first five Melbourne CBD demo venues.
            foreach ($venues->take(5) as $venue) {
                DB::table('venue_managers')->insert([
                    'user_id' => $publican->id, 'venue_id' => $venue->id, 'business_name' => 'Demo Hospitality Pty Ltd',
                    'business_email' => 'publican@'.config('beercheck.demo.email_domain'), 'status' => VenueManagerStatus::Approved->value,
                    'business_email_verified_at' => $now, 'reviewed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $venue->forceFill([
                    'phone' => '(03) 9000 '.str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'website' => 'https://example.com',
                    'description' => 'Demo venue for testing Beercheck. Not a real pub.',
                    'opening_hours' => ['mon' => '12pm – 11pm', 'tue' => '12pm – 11pm', 'wed' => '12pm – 11pm', 'thu' => '12pm – late',
                        'fri' => '12pm – 1am', 'sat' => '11am – 1am', 'sun' => '11am – 10pm'],
                ])->save();
            }

            $prices = 0;
            $points = [];
            $events = [];
            $replies = [];
            foreach ($venues as $venue) {
                $level = mt_rand(88, 115) / 100;                          // venue price level
                $level *= $venue->state === 'NSW' ? 1.05 : 1.0;           // Sydney premium
                $tap = $beers->random(min($beers->count(), mt_rand(5, 10)));

                foreach ($tap as $beer) {
                    $premium = match ($beer->type->name) {
                        'IPA', 'Sour' => 1.15, 'Pale Ale', 'Stout', 'Wheat' => 1.07, 'Mid-strength' => .92, default => 1.0,
                    };
                    $sizes = Arr::random(['pot', 'schooner', 'pint', 'jug', 'can', 'stubby'], mt_rand(1, 3));
                    foreach ((array) $sizes as $size) {
                        [$lo, $hi] = self::PRICE_RANGES[$size];
                        $official = mt_rand(1, 100) <= 25;
                        $posts = $official ? 1 : mt_rand(1, 3);
                        for ($p = 0; $p < $posts; $p++) {
                            $base = ($lo + ($hi - $lo) * mt_rand(0, 100) / 100) * $level * $premium;
                            $price = round(round($base * 10) / 10 - ($p * 0.5 * mt_rand(0, 1)), 2);
                            $user = $official ? $publican : Arr::random($punters);
                            $created = $now->copy()->subDays(mt_rand(0, 75))->subMinutes(mt_rand(0, 1440));
                            $rating = (! $official && mt_rand(1, 100) <= 60) ? $this->weightedRating() : null;
                            $review = ($rating && mt_rand(1, 100) <= 70) ? Arr::random(self::REVIEWS[$rating]) : null;
                            $photo = (! $official && mt_rand(1, 100) <= 35) ? $this->pickPhoto($photos, $beer->type->name) : null;

                            $id = DB::table('beer_prices')->insertGetId([
                                'beer_id' => $beer->id, 'venue_id' => $venue->id, 'user_id' => $user->id,
                                'price' => max(3.5, $price), 'size' => $size, 'is_venue_listed' => $official,
                                'photo_path' => $photo, 'rating' => $rating, 'review_text' => $review,
                                'is_flagged' => false, 'created_at' => $created, 'updated_at' => $created,
                            ]);
                            $prices++;

                            if (! $official) {
                                foreach (array_filter([
                                    PointsEventType::PricePosted->value => true,
                                    PointsEventType::PhotoUploaded->value => (bool) $photo,
                                    PointsEventType::ReviewWritten->value => $review && mb_strlen($review) >= config('beercheck.points.review_min_chars'),
                                ]) as $type => $_) {
                                    $pts = (int) config("beercheck.points.{$type}");
                                    $events[] = ['user_id' => $user->id, 'event_type' => $type, 'points_awarded' => $pts,
                                        'reference_type' => 'beer_price', 'reference_id' => $id, 'created_at' => $created, 'updated_at' => $created];
                                    $points[$user->id] = ($points[$user->id] ?? 0) + $pts;
                                }
                            }

                            if ($review && $venue->managers()->exists() && mt_rand(1, 100) <= 60) {
                                $replies[] = ['beer_price_id' => $id, 'user_id' => $publican->id, 'reply_text' => Arr::random(self::REPLIES),
                                    'created_at' => $created->copy()->addHours(mt_rand(2, 30)), 'updated_at' => $created];
                            }
                        }
                    }
                }
            }

            foreach (array_chunk($events, 500) as $chunk) {
                DB::table('points_events')->insert($chunk);
            }
            if ($replies) {
                DB::table('review_replies')->insert($replies);
            }
            foreach ($points as $userId => $total) {
                DB::table('users')->where('id', $userId)->update(['points' => $total]);
            }

            return ['venues' => $venues->count(), 'users' => count($punters) + 1, 'prices' => $prices, 'replies' => count($replies)];
        });
    }

    /** @return array<string, int> */
    public function remove(): array
    {
        $prefix = config('beercheck.demo.external_prefix');
        $domain = config('beercheck.demo.email_domain');

        return DB::transaction(function () use ($prefix, $domain) {
            $venueIds = Venue::query()->where('external_id', 'like', $prefix.'%')->pluck('id');
            $userIds = User::query()->where('email', 'like', '%@'.$domain)->pluck('id');
            $priceIds = DB::table('beer_prices')->whereIn('venue_id', $venueIds)->orWhereIn('user_id', $userIds)->pluck('id');

            DB::table('review_replies')->whereIn('beer_price_id', $priceIds)->orWhereIn('user_id', $userIds)->delete();
            DB::table('points_events')->whereIn('user_id', $userIds)->delete();
            $prices = DB::table('beer_prices')->whereIn('id', $priceIds)->delete();
            DB::table('venue_managers')->whereIn('venue_id', $venueIds)->orWhereIn('user_id', $userIds)->delete();
            $venues = DB::table('venues')->whereIn('id', $venueIds)->delete();
            DB::table('beers')->whereIn('submitted_by', $userIds)->update(['submitted_by' => null]);
            $users = DB::table('users')->whereIn('id', $userIds)->delete();
            Storage::disk('public')->deleteDirectory('photos/demo');

            return compact('venues', 'users', 'prices');
        });
    }

    /** @return Collection<int, Venue> */
    private function createVenues()
    {
        $used = [];
        $venues = collect();
        $n = 0;
        foreach (self::SUBURBS as [$suburb, $state, $postcode, $lat, $lng, $count]) {
            for ($i = 0; $i < $count; $i++) {
                do {
                    $name = 'The '.Arr::random(self::ADJECTIVES).' '.Arr::random(self::NOUNS).' '.Arr::random(self::SUFFIXES);
                } while (isset($used[$name]));
                $used[$name] = true;

                // Scatter ~0-900 m around the suburb centre.
                $dist = mt_rand(50, 900) / 1000;
                $bearing = deg2rad(mt_rand(0, 359));
                $vLat = $lat + rad2deg($dist * 1000 * cos($bearing) / Geo::EARTH_RADIUS_M);
                $vLng = $lng + rad2deg($dist * 1000 * sin($bearing) / Geo::EARTH_RADIUS_M / cos(deg2rad($lat)));

                $venue = new Venue([
                    'name' => $name, 'address' => mt_rand(1, 420).' '.Arr::random(self::STREETS), 'suburb' => $suburb,
                    'state' => $state, 'postcode' => $postcode, 'latitude' => round($vLat, 7), 'longitude' => round($vLng, 7),
                    'external_id' => config('beercheck.demo.external_prefix').(++$n), 'licence_type' => 'Demo venue',
                    'description' => 'Demo venue for testing Beercheck. Not a real pub.', 'is_active' => true,
                ]);
                $venue->source = VenueSource::Manual;
                $venue->save();
                $venues->push($venue);
            }
        }

        return $venues;
    }

    /** @return array<string, User> */
    private function createUsers(): array
    {
        $domain = config('beercheck.demo.email_domain');
        $password = Hash::make(str()->random(40)); // demo accounts can't be logged into
        $users = [];
        foreach (self::PUNTERS as $username) {
            $user = new User(['name' => ucwords(str_replace('_', ' ', $username)), 'username' => $username,
                'email' => "{$username}@{$domain}", 'date_of_birth' => now()->subYears(mt_rand(19, 60))->toDateString(), 'password' => $password]);
            $user->email_verified_at = now();
            $user->save();
            $users[$username] = $user;
        }
        $publican = new User(['name' => 'Demo Publican', 'username' => 'demo_publican', 'email' => "publican@{$domain}",
            'date_of_birth' => '1980-01-01', 'password' => $password]);
        $publican->role = UserRole::Publican;
        $publican->save();
        $users['__publican'] = $publican;

        return $users;
    }

    /** Copy the bundled illustrations to the public disk. @return array<string, list<string>> style => paths */
    private function publishPhotos(): array
    {
        $out = [];
        foreach (glob(database_path('seeders/demo-photos/*.jpg')) as $file) {
            $path = 'photos/demo/'.basename($file);
            Storage::disk('public')->put($path, file_get_contents($file));
            $style = preg_replace('/-\d+\.jpg$/', '', basename($file));
            $out[$style][] = $path;
        }

        return $out;
    }

    private function pickPhoto(array $photos, string $type): ?string
    {
        $style = self::PHOTO_STYLES[$type] ?? 'lager';

        return isset($photos[$style]) ? Arr::random($photos[$style]) : null;
    }

    private function weightedRating(): int
    {
        $r = mt_rand(1, 100);

        return $r <= 40 ? 5 : ($r <= 75 ? 4 : ($r <= 90 ? 3 : ($r <= 97 ? 2 : 1)));
    }
}
