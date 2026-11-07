<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\MenuImportService;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Console\Command;

class ImportMenu extends Command
{
    protected $signature = 'app:import-menu
        {source : Path to a .csv/.xlsx file, or a Google Sheets link}
        {--dry-run : Validate and report without writing}
        {--punter : Import as punter-style prices instead of official venue prices}
        {--user= : Admin email/username to attribute the import to}';

    protected $description = 'Import venue drink menus (venue, brand, beer, size, price) from a spreadsheet';

    public function handle(MenuImportService $imports, SpreadsheetReader $reader): int
    {
        $source = $this->argument('source');
        $isUrl = preg_match('#^https?://#', $source) === 1;
        $path = $isUrl ? $reader->downloadSheet($source) : $source;
        $format = $isUrl ? 'csv' : strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $user = $this->option('user')
            ? User::where('email', $this->option('user'))->orWhere('username', $this->option('user'))->first()
            : User::where('role', 'admin')->orderBy('id')->first();

        $r = $imports->import($path, $format, $source, $user, (bool) $this->option('dry-run'), ! $this->option('punter'));
        if ($isUrl) {
            @unlink($path);
        }

        $this->table(['Rows', 'Prices '.($this->option('dry-run') ? 'to import' : 'imported'), 'New beers', 'Unchanged', 'Errors'], [[
            $r['rows_read'], $r['prices_imported'], $r['beers_created'], $r['duplicates_skipped'], count($r['errors']),
        ]]);
        foreach (array_slice($r['errors'], 0, 30) as $e) {
            $this->components->warn("Row {$e['row']}: {$e['message']}");
        }

        return self::SUCCESS;
    }
}
