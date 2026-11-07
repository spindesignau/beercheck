@extends('layouts.app', ['title' => 'Preview import · Admin'])

@section('content')
@include('admin._nav')

<div class="card p-5">
    <h1 class="text-2xl">Preview: {{ \Illuminate\Support\Str::limit($source, 60) }}</h1>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['Rows read' => $result['rows_read'], 'Prices to import' => $result['prices_imported'], 'New beers' => $result['beers_created'], 'Rows with errors' => count($result['errors'])] as $label => $value)
            <div class="rounded-2xl bg-foam p-3 ring-1 ring-ink/5">
                <div class="font-display text-2xl {{ $label === 'Rows with errors' && $value ? 'text-red-600' : '' }}">{{ $value }}</div>
                <div class="text-xs font-semibold text-ink/60">{{ $label }}</div>
            </div>
        @endforeach
    </div>
    @if ($result['duplicates_skipped'])<p class="mt-3 text-sm text-ink/60">{{ $result['duplicates_skipped'] }} rows match current prices and will be skipped.</p>@endif

    @if ($result['errors'])
        <details class="mt-4 rounded-2xl bg-red-50 p-3 text-sm ring-1 ring-red-200" open>
            <summary class="cursor-pointer font-bold text-red-700">Rows that will be skipped ({{ count($result['errors']) }})</summary>
            <ul class="mt-2 max-h-64 space-y-0.5 overflow-auto">@foreach (array_slice($result['errors'], 0, 200) as $e)<li><b>Row {{ $e['row'] }}:</b> {{ $e['message'] }}</li>@endforeach</ul>
        </details>
    @endif

    @if ($result['preview'])
        <h2 class="mb-2 mt-5 text-lg">First {{ count($result['preview']) }} prices</h2>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-ink/60"><tr><th class="py-2">Row</th><th>Venue</th><th>Beer</th><th>Size</th><th class="text-right">Price</th></tr></thead>
            <tbody>
            @foreach ($result['preview'] as $p)
                <tr class="border-t border-ink/5">
                    <td class="py-2 text-ink/50">{{ $p['row'] }}</td><td>{{ $p['venue'] }}</td>
                    <td>{{ $p['beer'] }} @if($p['new_beer'])<span class="badge-punter">new</span>@endif</td>
                    <td>{{ $p['size'] }}</td><td class="text-right font-bold">${{ number_format($p['price'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif

    <div class="mt-5 flex flex-wrap gap-3">
        @if ($result['prices_imported'] > 0)
            <form method="POST" action="{{ route('admin.imports.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="as_official" value="{{ $asOfficial ? 1 : 0 }}">
                <button class="btn-beer">Import {{ $result['prices_imported'] }} prices</button>
            </form>
        @endif
        <a href="{{ route('admin.imports.index') }}" class="btn-ghost">Cancel</a>
    </div>
</div>
@endsection
