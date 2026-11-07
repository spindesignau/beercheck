<?php

namespace App\Services\Import;

use App\Enums\BeerSize;
use App\Enums\BeerStatus;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\BeerType;
use App\Models\MenuImport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports venue drink menus from a spreadsheet.
 *
 * One row = one beer at one venue in one glass size:
 *   venue | suburb | postcode | brand | beer | type | abv | size | price   (venue_id optional)
 *
 * - Venues are matched (never created): by venue_id, else by name + postcode/suburb.
 * - Beers are matched by brand + name, and created (approved) if missing.
 * - Prices are written as official venue-listed prices by default, attributed
 *   to the importing admin, tagged with menu_import_id so a whole import can be undone.
 * - A row identical to the venue's current price for that beer/size is skipped.
 * - Imports never award points.
 */
class MenuImportService
{
    public const REQUIRED = ['brand', 'beer', 'size', 'price'];

    public const TEMPLATE_HEADERS = ['venue', 'suburb', 'postcode', 'venue_id', 'brand', 'beer', 'type', 'abv', 'size', 'price'];

    private const SIZE_ALIASES = [
        'middy' => 'pot', 'pot' => 'pot', '285' => 'pot', '285ml' => 'pot', 'handle' => 'pot', '7oz' => 'pot', '10oz' => 'pot',
        'schooner' => 'schooner', '425' => 'schooner', '425ml' => 'schooner', '15oz' => 'schooner',
        'pint' => 'pint', '570' => 'pint', '570ml' => 'pint', '20oz' => 'pint',
        'jug' => 'jug', 'pitcher' => 'jug', '1140ml' => 'jug', '1.14l' => 'jug',
        'stubby' => 'stubby', 'bottle' => 'stubby', 'stubbie' => 'stubby', '330ml' => 'stubby', '375ml' => 'stubby',
        'longneck' => 'longneck', 'long_neck' => 'longneck', 'tallie' => 'longneck', '750ml' => 'longneck',
        'can' => 'can', 'tin' => 'can', 'tinnie' => 'can', '440ml' => 'can',
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * @return array{import: ?MenuImport, rows_read: int, prices_imported: int, beers_created: int,
     *               duplicates_skipped: int, errors: list<array{row: int, message: string}>, preview: list<array<string, mixed>>}
     */
    public function import(string $path, string $format, string $sourceName, ?User $user, bool $dryRun = false, bool $asOfficial = true): array
    {
        $result = [
            'import' => null, 'rows_read' => 0, 'prices_imported' => 0, 'beers_created' => 0,
            'duplicates_skipped' => 0, 'errors' => [], 'preview' => [],
        ];

        $types = BeerType::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $otherTypeId = $types['other'] ?? $types->first();
        $venueCache = [];
        $beerCache = [];
        $newBeers = [];
        $rows = [];

        // Pass 1: read + validate everything (nothing is written if the file can't be read).
        foreach ($this->reader->rows($path, $format, self::REQUIRED) as $rowNumber => $row) {
            $result['rows_read']++;
            try {
                $rows[] = $this->validateRow($rowNumber, $row, $types->all(), $otherTypeId, $venueCache);
            } catch (ImportRowException $e) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }

        $run = function () use (&$result, $rows, $user, $dryRun, $asOfficial, $sourceName, &$beerCache, &$newBeers) {
            $import = $dryRun ? null : MenuImport::create([
                'user_id' => $user?->id, 'source_name' => Str::limit($sourceName, 250), 'as_official' => $asOfficial,
            ]);

            foreach ($rows as $r) {
                $key = mb_strtolower($r['brand'].'|'.$r['beer']);
                $beer = $beerCache[$key] ??= Beer::query()
                    ->whereRaw('LOWER(brand) = ? AND LOWER(name) = ?', [mb_strtolower($r['brand']), mb_strtolower($r['beer'])])
                    ->first();

                if (! $beer) {
                    $newBeers[$key] = true;
                    if (! $dryRun) {
                        $beer = new Beer(['brand' => $r['brand'], 'name' => $r['beer'], 'type_id' => $r['type_id'], 'abv' => $r['abv']]);
                        $beer->status = BeerStatus::Approved;
                        $beer->submitted_by = $user?->id;
                        $beer->reviewed_by = $user?->id;
                        $beer->reviewed_at = now();
                        $beer->save();
                        $beerCache[$key] = $beer;
                    }
                }

                if ($beer && $this->isUnchanged($r['venue']->id, $beer->id, $r['size'], $r['price'], $asOfficial)) {
                    $result['duplicates_skipped']++;

                    continue;
                }

                if (count($result['preview']) < 25) {
                    $result['preview'][] = [
                        'row' => $r['row'], 'venue' => $r['venue']->name.' ('.$r['venue']->suburb.')',
                        'beer' => $r['brand'].' '.$r['beer'], 'size' => $r['size'], 'price' => $r['price'], 'new_beer' => isset($newBeers[$key]),
                    ];
                }

                if (! $dryRun) {
                    $price = new BeerPrice(['beer_id' => $beer->id, 'venue_id' => $r['venue']->id, 'size' => $r['size'], 'price' => $r['price']]);
                    $price->user_id = $user?->id ?? User::query()->where('role', 'admin')->value('id');
                    $price->is_venue_listed = $asOfficial;
                    $price->menu_import_id = $import->id;
                    $price->save();
                }
                $result['prices_imported']++;
            }

            $result['beers_created'] = count($newBeers);

            if ($import) {
                $import->forceFill([
                    'rows_read' => $result['rows_read'],
                    'prices_imported' => $result['prices_imported'],
                    'beers_created' => $result['beers_created'],
                    'duplicates_skipped' => $result['duplicates_skipped'],
                    'error_count' => count($result['errors']),
                    'errors' => array_slice($result['errors'], 0, 200),
                ])->save();
                $result['import'] = $import;
            }
        };

        // All-or-nothing for the valid rows.
        $dryRun ? $run() : DB::transaction($run);

        return $result;
    }

    /** Soft-delete every price created by an import. */
    public function undo(MenuImport $import): int
    {
        return DB::transaction(function () use ($import) {
            $count = BeerPrice::query()->where('menu_import_id', $import->id)->delete();
            $import->forceFill(['status' => 'undone', 'undone_at' => now()])->save();

            return $count;
        });
    }

    /** @return array<string, mixed> */
    private function validateRow(int $rowNumber, array $row, array $types, int $otherTypeId, array &$venueCache): array
    {
        $get = fn (string $k) => isset($row[$k]) && trim((string) $row[$k]) !== '' ? trim((string) $row[$k]) : null;

        $brand = $get('brand');
        $beer = $get('beer');
        if (! $brand || ! $beer) {
            throw new ImportRowException('Brand and beer are required.');
        }
        // "Carlton | Carlton Draught" -> beer "Draught"
        if (Str::startsWith(mb_strtolower($beer), mb_strtolower($brand).' ')) {
            $beer = trim(mb_substr($beer, mb_strlen($brand)));
        }

        $sizeRaw = mb_strtolower(preg_replace('/\s+/', '', (string) $get('size')));
        $size = self::SIZE_ALIASES[$sizeRaw] ?? self::SIZE_ALIASES[preg_replace('/\(.*\)/', '', $sizeRaw)] ?? null;
        if (! $size || ! BeerSize::tryFrom($size)) {
            throw new ImportRowException("Unknown size \"{$get('size')}\". Use: ".implode(', ', BeerSize::values()).'.');
        }

        $priceRaw = str_replace([',', '$', ' ', 'AUD'], ['.', '', '', ''], (string) $get('price'));
        if (! is_numeric($priceRaw) || (float) $priceRaw < config('beercheck.prices.min') || (float) $priceRaw > config('beercheck.prices.max')) {
            throw new ImportRowException("Price \"{$get('price')}\" is not a valid amount.");
        }

        $abv = $get('abv');
        $abv = $abv !== null ? (float) rtrim($abv, '% ') : null;
        if ($abv !== null && ($abv < 0 || $abv > 20)) {
            $abv = null;
        }

        $typeName = mb_strtolower((string) $get('type'));
        $typeId = $types[$typeName] ?? $types[rtrim($typeName, 's')] ?? $otherTypeId;

        return [
            'row' => $rowNumber,
            'venue' => $this->resolveVenue($get('venue_id'), $get('venue'), $get('suburb'), $get('postcode'), $venueCache),
            'brand' => Str::limit($brand, 100, ''),
            'beer' => Str::limit($beer, 150, ''),
            'type_id' => $typeId,
            'abv' => $abv,
            'size' => $size,
            'price' => round((float) $priceRaw, 2),
        ];
    }

    private function resolveVenue(?string $id, ?string $name, ?string $suburb, ?string $postcode, array &$cache): Venue
    {
        $key = implode('|', [$id, mb_strtolower((string) $name), mb_strtolower((string) $suburb), $postcode]);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if ($id) {
            return $cache[$key] = Venue::query()->find((int) $id) ?? throw new ImportRowException("No venue with id {$id}.");
        }
        if (! $name) {
            throw new ImportRowException('Venue name (or venue_id) is required.');
        }

        /** @var Collection<int, Venue> $matches */
        $matches = Venue::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($postcode, fn ($q) => $q->where('postcode', $postcode))
            ->when(! $postcode && $suburb, fn ($q) => $q->whereRaw('LOWER(suburb) = ?', [mb_strtolower($suburb)]))
            ->limit(3)->get();

        return match ($matches->count()) {
            1 => $cache[$key] = $matches->first(),
            0 => throw new ImportRowException("Venue \"{$name}\"".($postcode || $suburb ? ' in '.($postcode ?: $suburb) : '').' not found. Check the spelling or add a venue_id.'),
            default => throw new ImportRowException("Venue \"{$name}\" matches several venues. Add a suburb/postcode or venue_id."),
        };
    }

    private function isUnchanged(int $venueId, int $beerId, string $size, float $price, bool $official): bool
    {
        $current = BeerPrice::query()->visible()
            ->where('venue_id', $venueId)->where('beer_id', $beerId)->where('size', $size)
            ->where('is_venue_listed', $official)
            ->latest('created_at')->latest('id')->value('price');

        return $current !== null && abs((float) $current - $price) < 0.005;
    }
}
