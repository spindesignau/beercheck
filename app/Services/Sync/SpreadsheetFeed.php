<?php

namespace App\Services\Sync;

use App\Enums\VenueSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Downloads a government CSV/XLSX export to a temp file and streams it row by
 * row (constant memory — important on shared hosting with low memory_limit).
 *
 * Government exports often have title/notes rows above the real header, so
 * the header row is detected as the first row containing a licence-number column.
 */
class SpreadsheetFeed implements VenueFeed
{
    private const HEADER_MARKERS = ['licence_number', 'licence_no', 'licence_num', 'licence_id', 'license_number'];

    public function __construct(
        private readonly VenueSource $source,
        private readonly string $location,       // URL or local path
        private readonly string $format = 'csv', // csv | xlsx
    ) {}

    public static function fromConfig(VenueSource $source): self
    {
        $cfg = config("beercheck.sync.sources.{$source->value}");
        if (blank($cfg['url'] ?? null)) {
            throw new RuntimeException("No feed URL configured for {$source->value} (set SYNC_".strtoupper(Str::before($source->value, '_')).'_URL).');
        }

        return new self($source, $cfg['url'], $cfg['format'] ?? 'csv');
    }

    public function source(): VenueSource
    {
        return $this->source;
    }

    public function rows(): iterable
    {
        [$path, $temporary] = $this->localCopy();

        try {
            $reader = $this->makeReader();
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : null), $row->toArray());

                    if ($headers === null) {
                        $normalised = array_map([self::class, 'normaliseHeader'], $cells);
                        if (array_intersect($normalised, self::HEADER_MARKERS)) {
                            $headers = $normalised;
                        }

                        continue;
                    }

                    if (! array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                        continue;
                    }

                    yield array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), null));
                }

                break; // first sheet only
            }

            $reader->close();
        } finally {
            if ($temporary) {
                @unlink($path);
            }
        }
    }

    public static function normaliseHeader(?string $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower((string) $header)), '_');
    }

    private function makeReader(): ReaderInterface
    {
        return $this->format === 'xlsx' ? new XlsxReader : new CsvReader;
    }

    /** @return array{0: string, 1: bool} */
    private function localCopy(): array
    {
        if (! preg_match('#^https?://#i', $this->location)) {
            return [$this->location, false];
        }

        $path = tempnam(sys_get_temp_dir(), 'venue_feed_').'.'.$this->format;
        Http::timeout(config('beercheck.sync.http_timeout'))
            ->withHeaders(['User-Agent' => config('beercheck.geocoding.user_agent')])
            ->sink($path)
            ->get($this->location)
            ->throw();

        return [$path, true];
    }
}
