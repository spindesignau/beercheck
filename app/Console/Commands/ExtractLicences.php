<?php

namespace App\Console\Commands;

use App\Enums\VenueSource;
use App\Services\Sync\LicenceRecordMapper;
use App\Services\Sync\SpreadsheetFeed;
use Illuminate\Console\Command;

/**
 * Turns a full government licence register (tens of thousands of rows, XLSX)
 * into a compact CSV of pubs/hotels/bars only, using the same filter as the
 * sync. The result is bundled in database/data/venues/{source}.csv and is what
 * app:sync-venues imports when no download URL is configured.
 */
class ExtractLicences extends Command
{
    protected $signature = 'app:licences:extract
        {source : nsw_liquor or vic_liquor}
        {file : Register export (.xlsx or .csv)}
        {--out= : Output CSV (default: database/data/venues/{source}.csv)}';

    protected $description = 'Extract pubs, hotels and bars from a NSW/VIC liquor licence register export into a compact CSV';

    private const COLUMNS = ['licence_number', 'licence_name', 'licence_type', 'status', 'trading_status', 'business_type',
        'address', 'suburb', 'postcode', 'latitude', 'longitude'];

    public function handle(): int
    {
        $source = VenueSource::tryFrom($this->argument('source'));
        if (! $source || $source === VenueSource::Manual) {
            $this->fail('Source must be nsw_liquor or vic_liquor.');
        }

        $file = $this->argument('file');
        $out = $this->option('out') ?: config('beercheck.sync.bundled_path')."/{$source->value}.csv";
        @mkdir(dirname($out), 0755, true);

        $feed = new SpreadsheetFeed($source, $file, strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xlsx' ? 'xlsx' : 'csv');
        $mapper = new LicenceRecordMapper($source);
        $read = $kept = $noCoords = 0;
        $types = [];

        $fh = fopen($out, 'w');
        fputcsv($fh, self::COLUMNS);
        foreach ($feed->rows() as $row) {
            $read++;
            $m = $mapper->map($row);
            if (! $m || ! $mapper->accepts($m)) {
                continue;
            }
            $kept++;
            $noCoords += (int) ($m['latitude'] === null);
            $types[$m['licence_type']] = ($types[$m['licence_type']] ?? 0) + 1;
            fputcsv($fh, [
                $m['external_id'], $m['name'], $m['licence_type'], $m['status'], $m['trading_status'], $m['business_type'],
                $m['address'], $m['suburb'], $m['postcode'], $m['latitude'], $m['longitude'],
            ]);
        }
        fclose($fh);

        arsort($types);
        $this->components->info("Read {$read} licences, kept {$kept} pubs/bars ({$noCoords} without coordinates) -> {$out}");
        $this->table(['Licence type', 'Venues'], collect($types)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}
