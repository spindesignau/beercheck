<?php

namespace App\Services\Sync;

use App\Models\GeocodeCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Address -> coordinates with a permanent DB cache (geocode_cache), so a
 * weekly sync only ever calls the external API for genuinely new addresses.
 * Defaults to OSM Nominatim and respects its 1 request/second policy.
 */
class GeocodingService
{
    private int $calls = 0;

    private float $lastCallAt = 0;

    public function resetBudget(): void
    {
        $this->calls = 0;
    }

    public function callsMade(): int
    {
        return $this->calls;
    }

    /** @return array{lat: float, lng: float}|null */
    public function geocode(string $address): ?array
    {
        $query = self::normalise($address);
        if ($query === '') {
            return null;
        }
        $hash = sha1($query);

        $cached = GeocodeCache::query()->where('query_hash', $hash)->first();
        if ($cached) {
            if ($cached->found) {
                return ['lat' => $cached->latitude, 'lng' => $cached->longitude];
            }
            if ($cached->updated_at->gt(now()->subDays(config('beercheck.geocoding.negative_ttl_days')))) {
                return null; // recent miss: don't hammer the API
            }
        }

        $cfg = config('beercheck.geocoding');
        if (! $cfg['enabled'] || $this->calls >= $cfg['max_per_run']) {
            return null;
        }

        $result = $this->lookup($query, $cfg);
        if ($result === false) {
            return null; // transport error: don't cache, retry next run
        }

        GeocodeCache::query()->updateOrCreate(['query_hash' => $hash], [
            'query' => mb_substr($query, 0, 500),
            'latitude' => $result['lat'] ?? null,
            'longitude' => $result['lng'] ?? null,
            'found' => $result !== null,
            'provider' => 'nominatim',
        ]);

        return $result;
    }

    public static function normalise(string $address): string
    {
        $address = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $address)));
        if ($address === '') {
            return '';
        }

        return str_contains($address, 'australia') ? $address : $address.', australia';
    }

    /** @return array{lat: float, lng: float}|null|false */
    private function lookup(string $query, array $cfg): array|null|false
    {
        $wait = $cfg['throttle_ms'] / 1000 - (microtime(true) - $this->lastCallAt);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        $this->calls++;
        $this->lastCallAt = microtime(true);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => $cfg['user_agent']])
                ->get($cfg['endpoint'], ['q' => $query, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'au']);

            if (! $response->ok()) {
                return false;
            }

            $hit = $response->json('0');
            if (! $hit || ! isset($hit['lat'], $hit['lon'])) {
                return null;
            }

            $lat = (float) $hit['lat'];
            $lng = (float) $hit['lon'];

            return LicenceRecordMapper::inAustralia($lat, $lng) ? ['lat' => $lat, 'lng' => $lng] : null;
        } catch (Throwable $e) {
            Log::warning('Geocoding failed', ['query' => $query, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
