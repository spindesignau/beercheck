<?php

namespace App\Services\Import;

use App\Services\Sync\SpreadsheetFeed;
use Generator;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Streams rows from a CSV/XLSX file (first sheet) as header => value arrays.
 * The header row is the first row that contains every required column,
 * so title rows above the table are skipped.
 */
class SpreadsheetReader
{
    /**
     * @param  list<string>  $requiredHeaders  normalised (snake_case) header names
     * @return Generator<int, array<string, string|null>> keyed by spreadsheet row number (1-based)
     */
    public function rows(string $path, string $format, array $requiredHeaders): Generator
    {
        $reader = match (strtolower($format)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new InvalidArgumentException("Unsupported file type [{$format}]. Use .csv or .xlsx."),
        };
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = array_map(
                        fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_scalar($v) ? trim((string) $v) : null),
                        $row->toArray(),
                    );

                    if ($headers === null) {
                        $normalised = array_map(fn ($h) => self::alias(SpreadsheetFeed::normaliseHeader($h)), $cells);
                        if (! array_diff($requiredHeaders, $normalised)) {
                            $headers = $normalised;
                        }

                        continue;
                    }
                    if (! array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                        continue;
                    }

                    yield $rowNumber => array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), null));
                }

                if ($headers === null) {
                    throw new InvalidArgumentException('Could not find a header row containing: '.implode(', ', $requiredHeaders).'.');
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    /** Friendly header aliases -> canonical column names. */
    public static function alias(string $header): string
    {
        return [
            'venue_name' => 'venue', 'pub' => 'venue', 'hotel' => 'venue', 'bar' => 'venue',
            'brewery' => 'brand', 'beer_brand' => 'brand',
            'beer_name' => 'beer', 'product' => 'beer', 'drink' => 'beer',
            'style' => 'type', 'beer_type' => 'type',
            'glass' => 'size', 'serve' => 'size', 'glass_size' => 'size',
            'price_aud' => 'price', 'cost' => 'price', 'price_inc_gst' => 'price',
            'post_code' => 'postcode',
        ][$header] ?? $header;
    }

    /**
     * Turn a Google Sheets share/edit URL into its CSV export URL, download it
     * to a temp file and return the path. The sheet must be shared as
     * "Anyone with the link can view" (or published to the web).
     */
    public function downloadSheet(string $url): string
    {
        if (preg_match('#docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)#', $url, $m) && ! str_contains($url, 'output=csv') && ! str_contains($url, 'format=csv')) {
            $gid = preg_match('/[#&?]gid=(\d+)/', $url, $g) ? $g[1] : '0';
            $url = "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=csv&gid={$gid}";
        }

        $path = tempnam(sys_get_temp_dir(), 'menu_').'.csv';
        $response = Http::timeout(60)->withOptions(['allow_redirects' => true])->sink($path)->get($url);

        $type = (string) $response->header('Content-Type');
        if (! $response->ok() || str_contains($type, 'text/html')) {
            @unlink($path);
            throw new InvalidArgumentException('Could not download that sheet as CSV. In Google Sheets use Share → "Anyone with the link can view", then paste the link again.');
        }

        return $path;
    }
}
