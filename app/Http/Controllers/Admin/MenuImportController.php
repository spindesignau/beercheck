<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuImport;
use App\Services\Import\MenuImportService;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu spreadsheet import: upload (or Google Sheets link) -> preview -> confirm.
 * The uploaded file is kept in private storage between preview and confirm.
 */
class MenuImportController extends Controller
{
    private const DIR = 'menu-imports';

    public function __construct(private readonly MenuImportService $imports, private readonly SpreadsheetReader $reader) {}

    public function index(): View
    {
        return view('admin.imports.index', [
            'imports' => MenuImport::with('user:id,username')->latest()->paginate(20),
            'columns' => MenuImportService::TEMPLATE_HEADERS,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required_without:sheet_url', 'nullable', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'sheet_url' => ['required_without:file', 'nullable', 'url:https', 'max:500'],
            'as_official' => ['nullable', 'boolean'],
        ]);

        try {
            if ($request->hasFile('file')) {
                $ext = strtolower($request->file('file')->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
                $token = Str::uuid().'.'.$ext;
                $request->file('file')->storeAs(self::DIR, $token, 'local');
                $source = $request->file('file')->getClientOriginalName();
            } else {
                $tmp = $this->reader->downloadSheet($data['sheet_url']);
                $token = Str::uuid().'.csv';
                Storage::disk('local')->put(self::DIR.'/'.$token, file_get_contents($tmp));
                @unlink($tmp);
                $source = $data['sheet_url'];
            }

            $result = $this->run($token, $source, $request, dryRun: true);
        } catch (InvalidArgumentException|IOException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        return view('admin.imports.preview', [
            'result' => $result,
            'token' => $token,
            'source' => $source,
            'asOfficial' => $request->boolean('as_official', true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9a-f-]{36}\.(csv|xlsx)$/'],
            'source' => ['required', 'string', 'max:500'],
            'as_official' => ['nullable', 'boolean'],
        ]);
        abort_unless(Storage::disk('local')->exists(self::DIR.'/'.$data['token']), 410, 'Upload expired. Please upload the file again.');

        $result = $this->run($data['token'], $data['source'], $request, dryRun: false);
        Storage::disk('local')->delete(self::DIR.'/'.$data['token']);

        return redirect()->route('admin.imports.index')->with('status', sprintf(
            'Imported %d prices (%d new beers, %d unchanged skipped, %d rows with errors).',
            $result['prices_imported'], $result['beers_created'], $result['duplicates_skipped'], count($result['errors']),
        ));
    }

    public function undo(MenuImport $import): RedirectResponse
    {
        abort_if($import->status === 'undone', 422, 'Already undone.');
        $count = $this->imports->undo($import);

        return back()->with('status', "Import undone: {$count} prices removed.");
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, MenuImportService::TEMPLATE_HEADERS);
            fputcsv($out, ['The Example Hotel', 'Fitzroy', '3065', '', 'Carlton', 'Draught', 'Lager', '4.6', 'pot', '7.50']);
            fputcsv($out, ['The Example Hotel', 'Fitzroy', '3065', '', 'Carlton', 'Draught', 'Lager', '4.6', 'schooner', '10.50']);
            fputcsv($out, ['The Example Hotel', 'Fitzroy', '3065', '', 'Coopers', 'Pale Ale', 'Pale Ale', '4.5', 'pint', '13.00']);
            fclose($out);
        }, 'beercheck-menu-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function run(string $token, string $source, Request $request, bool $dryRun): array
    {
        $path = Storage::disk('local')->path(self::DIR.'/'.$token);

        return $this->imports->import(
            $path, pathinfo($token, PATHINFO_EXTENSION), $source, $request->user(), $dryRun, $request->boolean('as_official', true),
        );
    }
}
