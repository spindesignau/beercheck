<?php

namespace App\Services\Sync;

use App\Enums\VenueSource;
use Illuminate\Support\Str;

/**
 * Maps a raw licence row to venue attributes and decides whether the licence
 * is a pub / hotel / bar equivalent. Column names differ between NSW & VIC
 * exports (and change over time), so each field accepts several aliases.
 */
class LicenceRecordMapper
{
    private const ALIASES = [
        'external_id' => ['licence_number', 'licence_no', 'licence_num', 'licence_id', 'license_number'],
        'name' => ['trading_as', 'trading_name', 'licence_name', 'premises_name', 'venue_name', 'licensee'],
        'licence_type' => ['licence_type', 'licence_type_name', 'category', 'licence_category', 'licence_class'],
        'status' => ['status', 'licence_status'],
        'address' => ['address', 'premises_address', 'street_address', 'address_line_1'],
        'suburb' => ['suburb', 'premises_suburb', 'locality', 'town', 'suburb_town'],
        'postcode' => ['postcode', 'premises_postcode', 'post_code'],
        'latitude' => ['latitude', 'lat', 'y'],
        'longitude' => ['longitude', 'long', 'lng', 'lon', 'x'],
    ];

    public function __construct(private readonly VenueSource $source) {}

    /** @return array<string, mixed>|null null when the row is unusable */
    public function map(array $row): ?array
    {
        $get = function (string $field) use ($row): ?string {
            foreach (self::ALIASES[$field] as $alias) {
                if (isset($row[$alias]) && trim((string) $row[$alias]) !== '') {
                    return trim((string) $row[$alias]);
                }
            }

            return null;
        };

        $externalId = $get('external_id');
        $name = $get('name');
        if (! $externalId || ! $name) {
            return null;
        }

        $lat = is_numeric($get('latitude')) ? (float) $get('latitude') : null;
        $lng = is_numeric($get('longitude')) ? (float) $get('longitude') : null;
        if ($lat !== null && $lng !== null && ! self::inAustralia($lat, $lng)) {
            $lat = $lng = null; // bad/zero coordinates -> geocode instead
        }

        $address = $get('address');
        $suburb = $get('suburb');
        $postcode = $get('postcode');
        $postcode = $postcode && preg_match('/\b(\d{4})\b/', $postcode, $m) ? $m[1] : null;

        return [
            'external_id' => Str::limit($externalId, 64, ''),
            'name' => Str::limit(Str::title(mb_strtolower($name)), 255, ''),
            'licence_type' => Str::limit((string) $get('licence_type'), 120, ''),
            'status' => $get('status'),
            'address' => $address ? Str::limit(Str::title(mb_strtolower($address)), 255, '') : null,
            'suburb' => $suburb ? Str::limit(Str::title(mb_strtolower($suburb)), 100, '') : null,
            'state' => $this->source->state(),
            'postcode' => $postcode,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /** Pub / hotel / bar filter, driven by config so it can be tuned without code changes. */
    public function accepts(array $mapped): bool
    {
        $cfg = config("beercheck.sync.sources.{$this->source->value}");
        $type = mb_strtolower((string) $mapped['licence_type']);
        $status = mb_strtolower((string) ($mapped['status'] ?? ''));

        if (! empty($cfg['active_statuses']) && ! in_array($status, $cfg['active_statuses'], true)) {
            return false;
        }

        foreach ($cfg['exclude_licence_types'] as $needle) {
            if (str_contains($type, $needle)) {
                return false;
            }
        }

        foreach ($cfg['include_licence_types'] as $needle) {
            if (str_contains($type, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function inAustralia(float $lat, float $lng): bool
    {
        return $lat >= -44.0 && $lat <= -9.0 && $lng >= 112.0 && $lng <= 154.0;
    }
}
