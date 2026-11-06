<?php

namespace App\Services\Sync;

use App\Enums\VenueSource;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSyncRun;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Liquor licence -> venues sync.
 *
 *  1. stream rows from the feed
 *  2. map + filter to pubs / hotels / bars
 *  3. geocode rows lacking coordinates (cached)
 *  4. upsert in batches on (source, external_id)
 *  5. soft-deactivate venues of this source not seen in this run
 *     (never deletes — protects prices & reviews), guarded against truncated feeds
 */
class VenueSyncService
{
    private const BATCH_SIZE = 500;

    public function __construct(private readonly GeocodingService $geocoder) {}

    public function run(VenueSource $source, ?VenueFeed $feed = null, bool $dryRun = false, ?User $triggeredBy = null): VenueSyncRun
    {
        $startedAt = Carbon::now()->startOfSecond();
        $run = VenueSyncRun::create([
            'source' => $source->value,
            'status' => 'running',
            'dry_run' => $dryRun,
            'triggered_by' => $triggeredBy?->id,
            'started_at' => $startedAt,
            'fetched_count' => 0, 'filtered_count' => 0, 'inserted_count' => 0, 'updated_count' => 0,
            'deactivated_count' => 0, 'geocoded_count' => 0, 'skipped_count' => 0,
        ]);

        $this->geocoder->resetBudget();
        $mapper = new LicenceRecordMapper($source);
        $accepted = 0;

        try {
            $feed ??= SpreadsheetFeed::fromConfig($source);
            $batch = [];

            foreach ($feed->rows() as $row) {
                $run->fetched_count++;
                $venue = $mapper->map($row);

                if (! $venue || ! $mapper->accepts($venue)) {
                    $run->filtered_count++;

                    continue;
                }

                if ($venue['latitude'] === null || $venue['longitude'] === null) {
                    $coords = $this->geocoder->geocode(implode(', ', array_filter([
                        $venue['address'], $venue['suburb'], $venue['state'], $venue['postcode'],
                    ])));
                    if (! $coords) {
                        $run->skipped_count++;
                        if ($run->skipped_count <= 50) {
                            $run->appendError("No coordinates for {$venue['external_id']} ({$venue['name']})");
                        }

                        continue;
                    }
                    $venue['latitude'] = $coords['lat'];
                    $venue['longitude'] = $coords['lng'];
                }

                $accepted++;
                $batch[$venue['external_id']] = $venue; // de-dupes repeated licence numbers

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->flush($source, $batch, $startedAt, $run, $dryRun);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->flush($source, $batch, $startedAt, $run, $dryRun);
            }

            $run->geocoded_count = $this->geocoder->callsMade();
            $this->deactivateMissing($source, $startedAt, $accepted, $run, $dryRun);

            $run->status = 'completed';
        } catch (Throwable $e) {
            $run->status = 'failed';
            $run->appendError($e::class.': '.$e->getMessage());
            report($e);
        } finally {
            $run->finished_at = now();
            $run->save();
        }

        return $run;
    }

    /** @param array<string, array<string, mixed>> $batch keyed by external_id */
    private function flush(VenueSource $source, array $batch, Carbon $syncedAt, VenueSyncRun $run, bool $dryRun): void
    {
        $existing = Venue::query()
            ->where('source', $source->value)
            ->whereIn('external_id', array_keys($batch))
            ->pluck('external_id')
            ->flip();

        $run->inserted_count += count($batch) - $existing->count();
        $run->updated_count += $existing->count();

        if ($dryRun) {
            return;
        }

        $spatial = Geo::supportsSpatial();
        $now = now();
        $rows = array_map(function (array $v) use ($source, $syncedAt, $now, $spatial) {
            $row = [
                'source' => $source->value,
                'external_id' => $v['external_id'],
                'name' => $v['name'],
                'address' => $v['address'],
                'suburb' => $v['suburb'],
                'state' => $v['state'],
                'postcode' => $v['postcode'],
                'latitude' => round($v['latitude'], 7),
                'longitude' => round($v['longitude'], 7),
                'licence_type' => $v['licence_type'],
                'is_active' => true, // applies to inserts only (not in the update list)
                'last_synced_at' => $syncedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($spatial) {
                $row['location'] = Geo::pointExpression($row['latitude'], $row['longitude']);
            }

            return $row;
        }, array_values($batch));

        $update = ['name', 'address', 'suburb', 'state', 'postcode', 'latitude', 'longitude', 'licence_type', 'last_synced_at', 'updated_at'];
        if ($spatial) {
            $update[] = 'location';
        }

        DB::transaction(function () use ($rows, $update, $source, $batch) {
            Venue::query()->upsert($rows, ['source', 'external_id'], $update);

            // Re-activate venues that this pipeline previously deactivated and that
            // have re-appeared. Venues an admin switched off stay off.
            Venue::query()
                ->where('source', $source->value)
                ->whereIn('external_id', array_keys($batch))
                ->where('is_active', false)
                ->where('deactivation_reason', 'sync_missing')
                ->update(['is_active' => true, 'deactivation_reason' => null, 'deactivated_at' => null]);
        });
    }

    private function deactivateMissing(VenueSource $source, Carbon $startedAt, int $accepted, VenueSyncRun $run, bool $dryRun): void
    {
        $missing = Venue::query()
            ->where('source', $source->value)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $startedAt));

        $activeBefore = Venue::query()->where('source', $source->value)->where('is_active', true)->count();
        $minRatio = config('beercheck.sync.min_feed_ratio');

        if ($activeBefore > 0 && $accepted < $activeBefore * $minRatio) {
            $run->appendError(sprintf(
                'Deactivation skipped: feed returned %d accepted venues vs %d active (< %d%%). Possible truncated feed.',
                $accepted, $activeBefore, $minRatio * 100,
            ));

            return;
        }

        $run->deactivated_count = $dryRun
            ? $missing->count()
            : $missing->update(['is_active' => false, 'deactivation_reason' => 'sync_missing', 'deactivated_at' => now()]);
    }
}
