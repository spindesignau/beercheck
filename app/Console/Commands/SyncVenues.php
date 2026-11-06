<?php

namespace App\Console\Commands;

use App\Enums\VenueSource;
use App\Services\Sync\SpreadsheetFeed;
use App\Services\Sync\VenueSyncService;
use Illuminate\Console\Command;

class SyncVenues extends Command
{
    protected $signature = 'app:sync-venues
        {--source=* : nsw_liquor and/or vic_liquor (default: all enabled sources)}
        {--file= : Import from a local CSV/XLSX instead of the configured URL (requires one --source)}
        {--format= : csv|xlsx for --file (default: from extension)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Import pubs, hotels and bars from the NSW and VIC liquor licence registers';

    public function handle(VenueSyncService $sync): int
    {
        $sources = collect($this->option('source'))
            ->whenEmpty(fn () => collect(config('beercheck.sync.sources'))->filter(fn ($c) => $c['enabled'])->keys())
            ->map(fn ($s) => VenueSource::tryFrom($s) ?? $this->fail("Unknown source [{$s}]."))
            ->reject(fn (VenueSource $s) => $s === VenueSource::Manual);

        if ($this->option('file') && $sources->count() !== 1) {
            $this->fail('--file requires exactly one --source.');
        }

        $failed = false;
        foreach ($sources as $source) {
            $this->components->info("Syncing {$source->value}".($this->option('dry-run') ? ' (dry run)' : ''));

            $feed = $this->option('file')
                ? new SpreadsheetFeed($source, $this->option('file'), $this->option('format') ?: strtolower(pathinfo($this->option('file'), PATHINFO_EXTENSION)))
                : null;

            $run = $sync->run($source, $feed, (bool) $this->option('dry-run'));

            $this->table(['Status', 'Fetched', 'Filtered out', 'Inserted', 'Updated', 'Deactivated', 'Geocoded', 'Skipped'], [[
                $run->status, $run->fetched_count, $run->filtered_count, $run->inserted_count,
                $run->updated_count, $run->deactivated_count, $run->geocoded_count, $run->skipped_count,
            ]]);

            if ($run->error_log) {
                $this->components->warn(str($run->error_log)->limit(2000));
            }
            $failed = $failed || $run->status === 'failed';
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
