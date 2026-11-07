@extends('layouts.app', ['title' => 'Menu import · Admin'])

@section('content')
@include('admin._nav')

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <form method="POST" action="{{ route('admin.imports.preview') }}" enctype="multipart/form-data" class="card space-y-4 p-5" x-data="{ mode: 'file' }">
        @csrf
        <h1 class="text-2xl">Import menu prices</h1>
        <p class="text-sm text-ink/70">One row per beer, per glass size, per venue. You'll see a preview before anything is saved.</p>

        <div class="flex rounded-full bg-foam p-1 text-sm font-bold ring-1 ring-ink/10">
            <button type="button" @click="mode = 'file'" :class="mode === 'file' && 'bg-ink text-white'" class="flex-1 rounded-full py-2">Upload file</button>
            <button type="button" @click="mode = 'sheet'" :class="mode === 'sheet' && 'bg-ink text-white'" class="flex-1 rounded-full py-2">Google Sheet</button>
        </div>

        <label x-show="mode === 'file'" class="block"><span class="label">Spreadsheet (.xlsx or .csv, max 10 MB)</span>
            <input type="file" name="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full text-sm">
        </label>
        <div x-show="mode === 'sheet'" x-cloak>
            <x-input label="Google Sheets link" name="sheet_url" type="url" placeholder="https://docs.google.com/spreadsheets/d/…" hint="Share the sheet as “Anyone with the link can view”. The first tab is imported (or the tab in the link)." />
        </div>

        <label class="flex items-start gap-3 text-sm">
            <input type="hidden" name="as_official" value="0">
            <input type="checkbox" name="as_official" value="1" checked class="mt-0.5 h-5 w-5 accent-[#B27800]">
            <span><b>Official venue prices</b>: show with the “Venue price” badge. Untick for prices you gathered yourselves.</span>
        </label>
        <button class="btn-beer w-full">Preview import</button>
    </form>

    <div class="card space-y-3 p-5 text-sm">
        <h2 class="text-lg">Spreadsheet format</h2>
        <p>Put these column headings in the first row (order doesn't matter, extra columns are ignored):</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($columns as $c)
                <code class="rounded-lg px-2 py-0.5 text-xs font-bold {{ in_array($c, ['brand', 'beer', 'size', 'price', 'venue']) ? 'bg-beer' : 'bg-foam ring-1 ring-ink/10' }}">{{ $c }}</code>
            @endforeach
        </div>
        <ul class="list-inside list-disc space-y-1 text-ink/80">
            <li><b>venue</b> must match a venue already in Beercheck. Add <b>suburb</b> or <b>postcode</b> if the name isn't unique, or use <b>venue_id</b> (shown in Admin → Venues).</li>
            <li><b>size</b>: pot/middy, schooner, pint, jug, stubby/bottle, longneck, can. Sizes like “425ml” work too.</li>
            <li><b>price</b>: “10.50” or “$10.50”.</li>
            <li>Beers that don't exist yet are created automatically (with <b>type</b> and <b>abv</b> if given).</li>
            <li>Rows identical to a venue's current price are skipped, so re-importing an updated menu is safe.</li>
        </ul>
        <a href="{{ route('admin.imports.template') }}" class="btn-ghost !py-2 text-sm">Download template (.csv)</a>
    </div>
</div>

<h2 class="mb-2 mt-6 text-lg">Import history</h2>
<div class="card overflow-x-auto">
<table class="w-full text-left text-sm">
    <thead class="bg-foam text-xs uppercase tracking-wide text-ink/60"><tr><th class="p-3">When</th><th>Source</th><th>By</th><th>Prices</th><th>New beers</th><th>Skipped</th><th>Errors</th><th></th></tr></thead>
    <tbody>
    @forelse ($imports as $import)
        <tr class="border-t border-ink/5 align-top">
            <td class="whitespace-nowrap p-3">{{ $import->created_at->timezone('Australia/Sydney')->format('d M Y H:i') }}</td>
            <td class="max-w-56 truncate p-3" title="{{ $import->source_name }}">{{ $import->source_name }} @unless($import->as_official)<span class="badge-punter">punter</span>@endunless</td>
            <td class="p-3">{{ $import->user?->username ?? '—' }}</td>
            <td class="p-3 font-bold">{{ $import->prices_imported }}</td>
            <td class="p-3">{{ $import->beers_created }}</td>
            <td class="p-3">{{ $import->duplicates_skipped }}</td>
            <td class="p-3">
                @if ($import->error_count)
                    <details><summary class="cursor-pointer font-bold text-red-600">{{ $import->error_count }}</summary>
                        <ul class="mt-1 max-h-40 overflow-auto text-xs">@foreach ($import->errors ?? [] as $e)<li>Row {{ $e['row'] }}: {{ $e['message'] }}</li>@endforeach</ul>
                    </details>
                @else 0 @endif
            </td>
            <td class="p-3 text-right">
                @if ($import->status === 'undone')
                    <span class="text-xs text-ink/50">undone</span>
                @else
                    <form method="POST" action="{{ route('admin.imports.undo', $import) }}" onsubmit="return confirm('Remove all {{ $import->prices_imported }} prices from this import?')">@csrf
                        <button class="text-xs font-bold text-red-600 underline">Undo</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="p-4 text-ink/60">No imports yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-3">{{ $imports->links() }}</div>
@endsection
